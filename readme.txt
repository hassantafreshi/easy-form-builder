=== Easy Form Builder by WhiteStudio - Drag & Drop Form Builder ===
Contributors: hassantafreshi, mostafas1990, 6arshid, aminkhadivar, presskopp, whitestudio
Donate link: https://whitestudio.team/donate-easy-form-builder/
Tags: form builder, contact form, email notification, multi step form, payment form
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 4.3.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Drag-and-drop WordPress forms with a built-in email delivery test, a tracking code for every entry, multi-step forms and free CSV export.

== Description ==

Easy Form Builder is a WordPress form plugin with a visual drag-and-drop form builder. Build contact forms, support and feedback forms, surveys and polls, payment forms (with a payment add-on such as Stripe or PayPal), login and registration forms, file upload forms and multi-step forms without writing code, with free custom email notification templates and a built-in visual editor.

[youtube https://www.youtube.com/watch?v=XBVyCFP36OY]

Every entry is stored in your own WordPress database. The plugin does not rely on external form services and does not send form submissions to third-party servers. The few remote services it can use, and when, are listed under External services.

== What Can This Form Plugin Do? ==

- Contact, support and feedback forms with a confirmation code and message tracking
- Survey and poll forms with charts of the results
- Payment, order, donation and booking forms (payment add-ons)
- Login (sign-in) and registration (sign-up) forms for WordPress users
- File upload forms, and audio, video and screen recording forms
- Multi-step forms, private forms and forms open only on a schedule
- Smart forms with conditional logic that show or hide fields based on answers
- Electronic signature forms for contracts and agreements
- Job application and event registration forms, from ready-made templates

== Core Features ==

= Drag & Drop Form Builder =
Add, remove and reorder fields without code, each with its own labels, placeholders, checks and display settings. Undo and Redo, form versions, field search and keyboard shortcuts are built in, and ready-made templates (contact, reservation, job application, event registration, quote request, booking, order and survey forms) get you started.

= Email Notification =
Send an email to the administrator, form managers or the visitor after each submission. Custom email notification templates are free: the built-in visual editor sets the subject, body, layout and submission details without HTML.

The email delivery check ("Check Email Server") sends a real test message the same way a form email is sent, waits for it to arrive and gives a deliverability score out of 100, with a report (spam score, SPF, DKIM, DMARC and advice) emailed to the site admin. The dashboard widget shows page views of form pages, submissions, sent and failed emails, and an **Email Error Log**.
[How to set up form notification emails in Easy Form Builder](https://whitestudio.team/document/how-to-set-up-form-notification-emails-in-easy-form-builder/)

= Confirmation Code (Tracking ID) =
Each submission gets a unique confirmation code that visitors and administrators use to find it, follow it and reply to it.
[How to activate a Confirmation Code in Easy Form Builder](https://whitestudio.team/document/how-to-activate-confirmation-code-in-easy-form-builder/)

= Form Validation and Spam Protection =
Every answer is checked in the browser and again on the server: required fields, formats, unique values, word limits, patterns, allowed e-mail domains, password strength, and one-time codes by e-mail or SMS. Errors are written under each field and read by screen readers.

Spam protection works without a CAPTCHA: a hidden trap field and a minimum time to fill, plus Akismet (when installed) and WordPress's own disallowed words; spam waits in its own tab. Google reCAPTCHA (v2, v2 invisible or v3) can be turned on for the site or per form. Cloudflare Turnstile, hCaptcha, ALTCHA, Question CAPTCHA and Friendly Captcha are add-ons (Free Plus and Pro).

= Form Limits, Scheduling and Save for Later =
Limit how many answers a form takes, open and close it on a schedule, limit answers per user, IP address, browser or e-mail address, and keep private forms for chosen user roles. Visitors can save their answers and continue later from a private link.

== Form Entry & Message Management Dashboard ==

Manage every submission in the WordPress admin: read it, reply from the dashboard with attachments, and keep the conversation with the visitor in one place. Entries load page by page with tabs (All, Unread, Starred, Payment pending, Closed, Spam, Trash) and search. Star an entry, add internal notes, see its history, close or reopen it, move it to the trash with Undo, print it, and export to CSV with the columns you choose. Free Plus adds editing an entry, resending its notification, Excel export, PDF downloads and statistics per form.

== Survey and Poll Forms ==

Rating stars, 5-point scales, Net Promoter Score (NPS) and Yes / No questions, with charts of the results and CSV export, for customer feedback, research and evaluations.

== Conditional Logic - Show or Hide Fields Based on User Answers ==

The visual conditional logic builder turns static forms into smart forms: show or hide fields and steps, make fields required or optional, set or clear values, jump to a step (skip logic), and send conditional email notifications to different recipients. Conditions compare text, choices, numbers and dates and combine with AND/OR. Rules are enforced on the server too, so hidden fields are validated and sanitized correctly.

Conditional logic is free in Free Plus (3 rules per form, 2 conditions per rule, 2 conditional email rules, Test mode). Pro removes the limits and adds nested groups, calculations, rule priority, conditional thank-you messages, redirects and webhooks, rule export and import, and the logic Inspector.

== Payment Forms ==

Payment gateways are add-ons, so your site only loads the gateway you use. Each adds a payment field to the builder:

- Stripe and PayPal (Free Plus and Pro), including recurring billing
- Persia Pay (ZarinPal) (Pro)
- Mollie, Payplug, Tap Payments, Omise, PayTabs, Paymob and Xendit (Free Plus and Pro)

The amount is always computed on the server. The Payments screen lists every payment with its history and refunds it when the gateway supports refunds.
[How to Create a payment form to collect online payments in Easy Form Builder](https://whitestudio.team/document/how-to-create-a-payment-form-in-easy-form-builder/)

== Login and Registration Forms ==

Create custom login and registration forms for WordPress users, with your own layout, checks and extra user fields, in place of the default WordPress login page.

== Advanced Form Fields ==

- Drag-and-drop file upload with several files, camera capture and private storage
- Audio, video and screen recorder fields: visitors record from the browser, and every recording is uploaded to your own site
- Signature (e-signature) fields; typed signatures with the Typed Signature add-on
- Name and address fields in each country's format; a map location picker (OpenStreetMap) with the Address & Postal add-on
- Country, state/province and city lists, bundled with the plugin
- Advanced date field (Gregorian, Persian, Hijri and more) with date limits and time; Jalali and Hijri date pickers
- Repeater groups, ranking, hidden fields with smart tags, collapsible sections and rich text
- Multiple select, range slider, rating, switch, telephone and mobile, color picker, and matrix and table fields
- Calculation fields and number formats (Numbers & Calculations add-on)

== Integrations and Add-ons ==

- Conditional Logic (free with Free Plus; more in Pro)
- Payment add-ons: Stripe, PayPal, Persia Pay (ZarinPal), Mollie, Payplug, Tap Payments, Omise, PayTabs, Paymob and Xendit
- CAPTCHA add-ons: Cloudflare Turnstile, hCaptcha, ALTCHA, Question CAPTCHA and Friendly Captcha
- Numbers & Calculations, Typed Signature, Address & Postal, Booking and Visitor Country
- SMS notifications (through the WP SMS plugin) and Telegram notifications
- Google Sheets: new entries become rows of your spreadsheet, with no Zapier or other connector
- Human Shield: invisible bot protection with signed tokens and rate limits, which keeps bots from using up your email and SMS quota
- Auto-Populate: fill fields from your CSV dataset or an external API
- Offline forms, and Jalali and Hijri date pickers
- Shield Security silentCAPTCHA integration
- Easy Form Builder Data Tools, a free separate plugin: export and import forms, import from Contact Form 7 and other form plugins, and backups

== Premium Features Included Free ==

Entries in your own database with the entries dashboard, email notifications with custom templates, file uploads, confirmation codes, multi-step forms, survey fields, CSV export and the weekly email deliverability test are free. Free Plus, also free of charge, adds conditional logic, the Stripe and PayPal add-ons, advanced fields and PDF downloads; its only difference is a small "Powered by Easy Form Builder" credit on the page that shows the form.

[Easy Form Builder is available in multiple versions](https://whitestudio.team/document/easy-form-builder-free-plus-activation-guide/).

== Frequently Asked Questions ==

= Why is my WordPress contact form not sending emails? =
In most cases the form works and the email is lost after WordPress hands it over: the host's mail server blocks or limits PHP mail, no SMTP service is set up, or the domain has no SPF, DKIM and DMARC records, so receiving mailboxes reject the message or file it as spam. In Easy Form Builder, open Panel > Settings > Email Settings and click "Check Email Server". The result tells you whether the test email arrived and what to fix next.
[Why form emails go to spam: hosting, not the plugin](https://whitestudio.team/document/why-form-emails-go-to-spam-hosting-not-plugin/)

= Why do my WordPress form emails go to spam? =
Receiving mailboxes trust a message when it is sent through an authenticated service and the domain publishes SPF, DKIM and DMARC records. A shared hosting server sending as your domain without these records looks like spoofing. The email delivery check shows your deliverability score, and the emailed report lists which of SPF, DKIM and DMARC are missing.

= How do I test whether my WordPress form emails are delivered? =
Click "Check Email Server" in Easy Form Builder's Email Settings. The plugin sends a real test message, waits until it arrives or the test expires, and shows a score out of 100 with one of four results: "Your email server is healthy", "Delivered, but likely to be filtered as spam", "Delivered, but deliverability is weak", or "No email arrived". A full report follows by email.

= Does Easy Form Builder guarantee that every email arrives? =
No plugin can. Delivery depends on your hosting, your sending service and your domain's DNS records. Easy Form Builder tests delivery, monitors sent and failed emails, logs errors, and tells you what to change. Every submission is also stored in your WordPress database, so no entry is lost when an email is.

= Can visitors check the status of their form submission? =
Yes. Each submission gets a confirmation code. Place the confirmation code finder on a page with the `[Easy_Form_Builder_confirmation_code_finder]` shortcode; the visitor enters the code to see the submission and your replies, and can reply from the same page.

= Does the email delivery check send data to an external service? =
Yes, to the WhiteStudio email testing service at whitestudio.team, which receives the test message and scores it. A check sends your site address, the sender and WordPress admin email addresses, the Easy Form Builder, WordPress and PHP versions, the site language and the plan type. The full report is emailed to the admin address. Checks run when you click "Check Email Server", once after activation if you skip the first-run check, after a plugin update, and once a week for the weekly report. Form submissions are never sent to this service.

= Is there a money-back guarantee on paid plans? =
Yes. Paid plans come with a 14-day money-back guarantee. The Free and Free Plus versions cost nothing.

= What is Easy Form Builder used for? =
It is a WordPress form plugin used to create contact forms, survey forms, payment forms, subscription forms, and login or registration forms using a drag-and-drop form builder, with a built-in check that tests whether the form emails arrive.

= Is Easy Form Builder WCAG (ADA) compliant? =
Form Builder has tried to add necessary standards to the forms, making them accessible to individuals with disabilities during form display.

 = Is my form data sent to external services? =
No. All form submissions and messages are stored locally on your WordPress website. Easy Form Builder does not send form data to external servers by default. The services the plugin can contact, and when, are listed under External services.

= Can I move my forms from Contact Form 7? =

Yes, with Easy Form Builder Data Tools, a free separate plugin (the forms panel offers to install it). Choose the forms and each one becomes a new Easy Form Builder form with its fields, choices, notification addresses and messages. A report lists anything that could not be carried over (for example quiz questions or hidden fields) and what to do instead. Your Contact Form 7 forms stay as they are. Data Tools also exports and imports Easy Form Builder forms and makes backups.

= Is coding knowledge required to build forms? =
No. All forms can be created visually without writing code.

= Does Easy Form Builder support email notifications? =
Yes. Email notifications can be sent automatically after form submission.
[How to set up form notification emails in Easy Form Builder](https://whitestudio.team/document/how-to-set-up-form-notification-emails-in-easy-form-builder/)

= Are custom email notification templates free? =
Yes. Easy Form Builder includes free custom email notification templates with a built-in visual editor. You can create and customize WordPress form email templates for admin notifications, user confirmations, support requests, surveys, registrations, and payment forms without coding or editing HTML manually.

= Does Easy Form Builder include an email log and delivery status check? =
Yes. Easy Form Builder includes a free email delivery status check and email activity log to help you confirm that WordPress form notification emails are being sent successfully. The email test sends a real test message, checks delivery status, gives a clear score and grade, and emails a complete report to the site admin with delivery details, spam score, SPF/DKIM/DMARC checks, and recommendations when improvements are needed. The Easy Form Builder dashboard widget also shows sent emails, failed emails, form submissions, page views for pages containing forms, and an Email Error Log for reviewing email sending errors.

= What is a confirmation code in Easy Form Builder? =
A confirmation code is a unique tracking ID generated for each form submission.
[How to activate a Confirmation Code in Easy Form Builder](https://whitestudio.team/document/how-to-activate-confirmation-code-in-easy-form-builder/)

= Can I manage and reply to form submissions from the dashboard? =
Yes. The plugin includes a message management dashboard with reply and file upload support.

= Can I download form responses as PDF or CSV? =
Yes. Form entries and responses can be downloaded in PDF and CSV formats.

= Does the form plugin support file uploads? =
Yes. Users can upload files through forms, and administrators can attach files in replies.

= Can I create payment forms with Stripe? =
Yes, with the Stripe add-on (Free Plus and Pro). Install it from Easy Form Builder > Add-ons; it adds the Stripe payment field to the builder and its own settings page for your keys.
[How to Create a payment form to collect online payments in Easy Form Builder](https://whitestudio.team/document/how-to-create-a-payment-form-in-easy-form-builder/)

= Does Easy Form Builder support PayPal payments? =
Yes. You can add and set up the PayPal payment field to accept online payments through your WordPress forms. The PayPal add-on supports one-time payments as well as recurring billing options including daily, weekly, monthly, and yearly subscriptions. Payment entries are managed alongside other form submissions in the Form Entry & Message Management Dashboard.

= How do I go back to version 4.3.1? =
Version 4.3.2 moves forms with the older Stripe, PayPal and Persia Pay fields to the add-ons' new payment fields, which 4.3.1 cannot show. Before you install 4.3.1, open Easy Form Builder > Payments > Moved forms and press "Move every form back". Edits made since the move are kept. Then install 4.3.1. Entries and payments stay as they are.

= Does Easy Form Builder support survey forms? =
Yes. The form builder includes survey and poll fields such as rating stars and NPS.

= Does Easy Form Builder include ready-made form templates? =
Yes. The plugin comes with pre-built templates for contact forms, reservation forms, job applications, event registrations, booking forms, quote requests, order forms, and more.


= Does Easy Form Builder support conditional logic? =
Yes. Easy Form Builder includes a visual conditional logic builder. You can show or hide fields and steps, make fields required or optional dynamically, set or clear values, jump to a specific step (skip logic), and send conditional email notifications - all without writing code. Conditional rules are also enforced on the server, so hidden fields are validated and sanitized correctly.

= Is conditional logic free? =
Yes. Conditional logic is available at no cost in the Free Plus version, which includes up to 3 logic rules per form, up to 2 conditions per rule, and up to 2 conditional email notification rules. The Pro version removes these limits and adds nested AND/OR groups, calculations, conditional redirects and thank-you messages, conditional webhooks, and the logic Inspector.

= What does the Free version include? =
The Free version provides the drag-and-drop builder with 20 form fields (including terms and conditions, country list, star rating and heading), multi-step forms, file uploads, email notifications with the visual template editor, the email delivery check, the weekly email health report and Email Error Log, confirmation codes with a public lookup page, entry management in your WordPress database, and CSV export.

This version is suitable for personal websites, blogs, and small business sites.

= What does the Free Plus version include? =
The Free Plus version extends the Free version by enabling advanced form fields and features available in the form builder. It also unlocks conditional logic at no cost - up to 3 logic rules per form, up to 2 conditions per rule, and up to 2 conditional email notification rules - plus payment forms with the Stripe, PayPal and other payment add-ons. CSV export and the weekly email deliverability report are included in every version.

This version is suitable for users who need advanced form layouts, smart conditional forms, and surveys without add-on-based extensions. A small "Powered by Easy Form Builder" credit is displayed on the form output.

= What does the Pro version include? =
The Pro version includes all Free Plus features and adds support for official add-ons and integrations. For conditional logic, Pro removes all rule limits and adds nested AND/OR groups, calculations, rule priority, conditional thank-you messages and redirects, conditional webhooks, rule export/import, and the logic Inspector (debugger).

This version is suitable for professional websites, service platforms, and projects that require extended workflows such as SMS notifications, Persian payment gateways, conditional workflows, and advanced integrations.

= Can I show or hide form fields based on a user's answer? =
Yes. Conditional fields are the core of the conditional logic builder: choose a condition (for example, "Plan is Enterprise") and an action (for example, "show the Budget field"), and the form updates in real time while the visitor fills it in.

= Can I send form notification emails to different recipients based on the answers? =
Yes. Conditional email notifications let you route each submission to the right person or team - for example, sales versus support - based on what the visitor selected in the form.

= Does conditional logic work with multi-step forms? =
Yes. You can show, hide, or skip entire steps and jump directly to a specific step (conditional branching), with correct Back/Next behavior and validation for hidden or skipped steps.

= Can Easy Form Builder auto-populate form fields? =
Yes. The Auto-Populate (Autofill) add-on can fill form fields automatically instead of asking visitors to type information you already have. Fields can be dynamically populated from your own datasets (CSV) with Auto-Populate Dataset, or from an external API with Auto-Populate Integrations. Both are official add-ons available in the Pro version.

= Can I populate form fields from a CSV file or my own dataset? =
Yes. With Auto-Populate Dataset, you upload a CSV file (UTF-8 with column headers in the first row) and connect it to a form. Form elements are mapped to dataset fields as search conditions, and the matching dataset columns automatically populate the target fields. Multiple fields can auto-populate from the same dataset, dataset values can be edited inline, and changes take effect immediately for new submissions.

= Can I auto-fill form fields from an external API? =
Yes. Auto-Populate Integrations connects a form to an external API endpoint and fills fields with real-time data. It supports API Key, Bearer Token, and Basic Auth authentication, lets you map API response fields to your form fields, includes a built-in test tool to verify the connection, and offers caching and timeout settings so forms keep loading fast.

= Does Easy Form Builder support recurring payments? =
Yes. The payment fields support one-time, daily, weekly, monthly, and yearly billing options for subscription and recurring payment forms. You can create payment forms using Stripe or PayPal to accept online payments directly through your WordPress forms. Both payment gateways are available as add-ons and can be configured from the Easy Form Builder settings.
[How to Create a payment form to collect online payments in Easy Form Builder](https://whitestudio.team/document/how-to-create-a-payment-form-in-easy-form-builder/)

= Can I send WordPress form submissions to Google Sheets? =
Yes. Easy Form Builder includes a Google Sheets integration that syncs form entries to a spreadsheet automatically - no Zapier or third-party connector required. You connect your site once with a Google service account, bind each form to a sheet tab with a simple wizard, and new submissions appear as rows, with the header row created automatically on the first submission. A built-in Google Sheet log shows every sync attempt.

= How can I stop WordPress form spam without using CAPTCHA? =
Use the Security & Spam Protection add-on in Easy Form Builder. It works invisibly - no puzzles or checkboxes - by scoring real browser behavior, a hidden honeypot field, signed single-use security tokens, and per-IP rate limits. Suspicious requests are logged, quarantined, or blocked depending on the mode you choose (Monitor only, Soft block, or Strict).

= Does the spam protection send visitor data to an external anti-spam service? =
No. All scoring, tokens, rate limits, and security logs are processed and stored on your own WordPress site. The logs keep hashed identifiers instead of raw IP addresses, and log retention is configurable (30 days by default).

= Can spam protection stop bots from wasting my email and SMS quota? =
Yes. Low-confidence submissions can be saved without triggering email, SMS, Telegram, webhook, or Google Sheets actions. Each channel also has a daily limit and a per-recipient cap, so bot traffic cannot burn through paid SMS credit or flood your inbox - while the entries stay available for review.

= Do the country, state, and city fields load data from an external source? =
No. Since 4.3.2 the country, state/province, and city lists come with the plugin and are served from your own site. They are a pinned copy of this public dataset (MIT license):
https://github.com/hassantafreshi/Json-List-of-countries-states-and-cities-in-the-world

Only a development copy of the plugin built without these files (for example a copy taken straight from GitHub) loads them from a CDN instead; see External services.

If you notice any missing entries, incorrect names, or data issues related to countries, states, or cities, please report the issue by creating a ticket in the GitHub repository above.

= Where can I find documentation and support? =
For full setup instructions and guides, visit [ Easy Form Builder Documentation](https://whitestudio.team/documents)

= What are the different confirmation code styles and how do I customize confirmation code (or tracking code) in my WordPress form submissions? =
Easy Form Builder WordPress plugin includes seven confirmation code (or tracking code) styles with locale-aware character support for submitted forms. Each tracking ID style can use characters from 55+ languages including Chinese, Japanese, Korean, Russian, Hebrew, Hindi, Persian, Arabic, Bengali, Gujarati, Punjabi, Tamil, Telugu, Kannada, Malayalam, Greek, Thai, Khmer, Lao, Myanmar, Georgian, Armenian, and more.

When users submit forms through the Easy Form Builder WordPress plugin, they receive a unique tracking code or confirmation code based on your chosen style. The seven available confirmation code styles for form submissions are:
- Date + Random Number
- Date + National Language Letters & Random Number
- Date + National Language Letters Only
- Date + English Characters
- Date + National Random Numbers
- Unique Number (date-based)
- National Language Letters & Random Number

In the Easy Form Builder plugin settings, you can choose your preferred confirmation code style from the form submission tracking options. The plugin automatically selects characters based on your website's language/locale, making it ideal for multilingual and RTL (right-to-left) language WordPress websites. This feature improves code readability and user experience when users submit forms, displaying confirmation codes and tracking IDs in the visitor's native language and script.


== External services ==

Entries stay in your WordPress database and are never sent to WhiteStudio or another company. The plugin contacts the services below only for the feature named. Form answers are not sent unless stated.

**Google reCAPTCHA** (www.google.com, www.gstatic.com, or www.recaptcha.net if chosen in Settings > Captchas): only where you turn reCAPTCHA on. The visitor's browser loads Google's script, which sends Google data about the visit; on submit your site sends Google your secret key and the visitor's token to verify it (no answers, no IP address). [Terms](https://policies.google.com/terms), [Privacy](https://policies.google.com/privacy).

**Google Fonts** (fonts.googleapis.com, fonts.gstatic.com) and **jsDelivr** (cdn.jsdelivr.net): administrators only. The admin screens load the Roboto font, and Settings > Response box loads the fonts you preview. Visitors' pages never load Google Fonts: the plugin includes the fonts. [Google Privacy](https://policies.google.com/privacy), [jsDelivr Privacy](https://www.jsdelivr.com/terms/privacy-policy).

**Your own font address**: a font stylesheet address you enter in Settings > Response box is loaded by your visitors' browsers from that address.

**Development copies only** (cdn.jsdelivr.net, cdn.easyformbuilder.ir): the WordPress.org release includes the country, state and city lists and Chart.js. A copy built without them (for example straight from GitHub) loads them in the visitor's browser from jsDelivr, or from WhiteStudio's mirror cdn.easyformbuilder.ir on Persian sites. No form data is sent. Persian (fa_IR) sites also check once an hour whether that mirror answers.

**WhiteStudio add-on server** (whitestudio.team; easyformbuilder.ir for Persian sites): when you install or update an add-on, or the plugin reinstalls your add-ons after an update. Sends the add-on's name, your site's domain and the WordPress and plugin versions.

**WhiteStudio license server** (whitestudio.team): when you enter a Pro activation code, and from time to time to check it. Sends the code, your site's domain, the admin e-mail address, the plugin, WordPress and PHP versions, the site language and the active plugins. Persian sites check the code locally and send nothing.

**WhiteStudio e-mail test service** (whitestudio.team, www.whitestudio.team as fallback): tests whether form e-mails arrive; see the FAQ "Does the email delivery check send data to an external service?". Never receives form submissions.

**Language packs** (whitestudio.team, api.wordpress.org, downloads.wordpress.org): when a language the plugin does not include is used (it includes English, Persian, Arabic and German), it downloads that translation. Sends the plugin's name and version and the language codes. Only translation files are downloaded, never code (the .l10n.php file is generated on your site from the downloaded .mo). For a language it installs itself, it removes WordPress.org's translation update offer for this plugin, so the two packs do not replace each other; plugin updates are never touched.

**Feedback and review reward** (whitestudio.team): only when you press Send in the optional deactivation survey (reason, message, e-mail if typed, plugin, WordPress and PHP versions) or ask for the review reward (WordPress.org user name, e-mail, site domain and language, plugin version).

WhiteStudio's terms and privacy policy: [whitestudio.team/privacy-policy-terms](https://whitestudio.team/privacy-policy-terms/). WordPress.org: [Privacy](https://wordpress.org/about/privacy/).

**Akismet** (through the Akismet plugin, when it is active): answers are checked for spam; the visitor's IP address only as your privacy setting allows. [Terms](https://akismet.com/tos/), [Privacy](https://automattic.com/privacy/).

**Webhooks you add**: a webhook rule of the Conditional Logic add-on sends the entry's answers to the address you enter.

**Add-ons** connect only to their own service, after you set them up: payment gateways (Stripe, PayPal, ZarinPal, Mollie, Payplug, Tap Payments, Omise, PayTabs, Paymob, Xendit), Google Sheets, Telegram, SMS through WP SMS, CAPTCHA providers (Cloudflare Turnstile, hCaptcha, Friendly Captcha), OpenStreetMap for address search (Address & Postal) and the IP location service you choose (Visitor Country).

== Installation ==

**Bootstrap Notice:**
If your theme uses Bootstrap, go to "Easy Form Builder" → "Settings" and enable the Bootstrap option.

1. Download [*Easy Form Builder*](https://downloads.wordpress.org/plugin/easy-form-builder.zip)
2. Navigate to Dashboard → "Plugins" → "Add New" → "Upload Plugin" → select *Easy Form Builder* zip file then "Activate".
3. In the menu bar, navigate to "Easy Form Builder" → "Settings" → "Add Google reCAPTCHA keys"
4. For local testing environments, you may use the key and secret below. For online websites, [click here to obtain the API keys for reCAPTCHA v2](https://www.google.com/recaptcha).


= Development Google reCAPTCHA keys for localhost =

**Site key:** `6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI`
**Secret key:** `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe`

[youtube https://www.youtube.com/watch?v=JI7RojBgU_o]


== Documentation & Support ==

For setup guides, tutorials, and feature walkthroughs, visit the official documentation:
[Easy Form Builder Documentation](https://whitestudio.team/documents)

For more information, visit the official website:
[WhiteStudio - Easy Form Builder](https://whitestudio.team)

== Screenshots ==

1. Form Builder
2. Create a form
3. Preview a form
4. Panel of Easy Form Builder
5. List of responses
6. Setting of Easy Form Builder
7. Display poll chart
8. Form submitted and Confirmation Code (tracking code)
9. Create a contact us form
10. Translate Easy Form Builder into your language
11. Email template Editor of Easy Form Builder
12. Order payment forms
13. Configuration Response box of Easy Form Builder
14. Email Setting of Easy Form Builder
15. SMS configuration of Easy Form Builder
16. Confirmation Code Search Field
17. WordPress Support Ticket Conversation and Reply Form
18. Easy Form Builder Dashboard Widget with email delivery status, form page views, completed submissions, sent and failed email counts, and Email Error Log
19. Test WordPress form notification emails for free with Easy Form Builder.
20. Customize the response form viewer to match your brand


== Upgrade Notice ==

= 4.3.2 =

Back up first. Requires WordPress 6.3+. Payment gateways (Stripe, PayPal, Persia Pay) are now add-ons: update them right after this release. The database update runs automatically.

= 4.3.1 =

Recommended update. Turns form emails on if they were off (you now receive each submission by email), verifies Stripe payments on the server and keeps form pages out of page caches. Purge your page cache and CDN after updating. Requires WordPress 5.7+ and PHP 7.4+.

= 4.3.0 =

Mandatory update. Improves form builder security and sets up the i18n translation and localization infrastructure. Back up your site before updating.

= 4.2.0 =

Recommended update. Adds an onboarding wizard, weekly email delivery reports and an Email Error Log, and updates the database. Back up your site first, then check email delivery, payment notifications and file uploads.

= 4.0.0 =

Major update: new form engine, Gutenberg block, page builder widgets and email template builder; database v1.1. Back up your site first. Not compatible with v3.x add-ons.

== Changelog ==

= 4.3.2 =

Requires WordPress 6.3 or later. The full list of changes, with the notes for developers, is in CHANGELOG.md in the plugin folder.

* New: Spam protection without a CAPTCHA (a hidden trap field and a minimum time to fill), Akismet and WordPress's disallowed words, and a Spam tab in the entries. Google reCAPTCHA v2, v2 invisible or v3 for the site or per form.
* New: Entries screen with tabs, search, stars, internal notes, history, trash with Undo, print, CSV export with the columns you choose, and per-form statistics. Edit an entry, resend its notification and export to Excel (Free Plus).
* New: Form limits and schedules, limits per user, IP address, browser or e-mail, private forms for chosen roles, "Save and continue later", custom CSS per form and popup forms.
* New: Field checks: unique values, word limits with a counter, patterns, allowed or blocked e-mail domains, read-only fields, prefix and suffix, password strength, "Confirm email", your own error messages, and one-time codes by e-mail or SMS.
* New fields and options: advanced date field (Gregorian, Persian, Hijri and more), several files per upload field with camera capture and private storage, hidden fields with smart tags and prefill from the page address, option values, bulk options, "Other", stock per option, ranking, repeater groups, review before submit, collapsible sections, rich text, and name and address fields in each country's format.
* New: Accessible forms: each error is written under its field and read by screen readers, an error summary, checks when a visitor leaves a field, browser autofill for every field and focus that follows the steps.
* New: In the form builder: Undo and Redo, form versions, field search and keyboard shortcuts; the field settings panel no longer covers the toolbar and its Save button.
* New: A Payments screen with every payment, its history and refunds, and new payment add-ons: Mollie, Payplug, Tap Payments, Omise, PayTabs, Paymob and Xendit.
* New: Privacy settings: store visitors' IP addresses anonymized or not at all, delete entries after a number of days, notification-only forms, and WordPress's personal data export and erase tools include entries.
* New: A form made by an assistant (AutoPilot) or an import can stay a draft, hidden from visitors, until you publish it.
* Changed: Stripe, PayPal and Persia Pay are add-ons now. Forms with their older payment fields move to the new fields automatically (subscription forms keep the older field). Update these add-ons right after this release.
* Changed: Numbers and calculations, typed signatures, postal-code formats and the map, booking editing, and the visitor's country are add-ons. Contact Form 7 import moved to the free Easy Form Builder Data Tools plugin.
* Changed: Fonts, the country, state and city lists and the poll and entry charts come from the plugin, not from Google Fonts, Google Charts or a CDN. See External services for what the plugin still contacts.
* Changed: {field_id} of a radio button or checkbox in an e-mail subject or redirect address now gives the option's label, not its ID ({field:id.value} gives its value).
* Changed: One notice at a time on the plugin's screens; the others wait behind a "Notices" bell. Help buttons open each add-on's own guide. The admin screens meet WCAG 2.1 AA contrast.
* Changed: Add-on settings screens share the plugin's look. The Security & Spam Protection add-on is now named "Human Shield". Paymob asks the visitor for a name and mobile number when the form has none.
* New: "Delete all data when the plugin is deleted" in Settings › Privacy (off by default: deleting the plugin keeps your forms and entries; when on, visitors' uploaded files go too). The Plugins screen says which. Deactivating the plugin removes its scheduled tasks.
* Security: Answers to password fields are never stored, e-mailed or exported; those saved by earlier versions are replaced by •••• after the update. Uploaded files are tied to the visitor and the field. Secret keys are write-only and no longer copied into an option WordPress loads on every page. The e-mail debug log moved to a private folder, and Tools › Site Health warns when that folder can be downloaded (nginx). Site-wide settings, add-ons and the Telegram and Google Sheets settings need an administrator.
* Faster form pages: about a third as many database queries, minified scripts and styles, and the response viewer loads only where it is needed.
* Fixed: Payments: back from a failed payment a form with steps opens the payment step with "Try again" (the same entry), the Payments screen stays readable without a payment add-on and fits a phone, and a moved payment form can be moved back without losing your edits.
* Fixed: An outdated add-on can be updated from the Add-ons page, which shows the add-ons within seconds when the add-on server cannot be reached. The rating invitation waits for real use and never interrupts an update.
* Fixed: Many fixes across forms, e-mails, entries, payments, booking forms, CAPTCHA, right-to-left screens and phones. See CHANGELOG.md.
* Developers: database version 1.13 (the update runs automatically); form package, builder (efbBuilderApi), payment, CAPTCHA and notices contracts. See CHANGELOG.md and the docs folder on GitHub.

= 4.3.1 =

* Improved: Forms use your theme's font; typed Jalali and Hijri dates are checked; offline answers come back after a reload; fixes for Stripe, PayPal, ZarinPal, SMS, Telegram, Google Sheets, Conditional Logic, Auto-Populate and the date pickers.
* Security: Stripe one-time payments are verified on the server, the Signature field's stored cross-site scripting issue is fixed, and every path that stores or shows visitor data is hardened.
* Changed: Form emails are on by default, and the owner's notification email includes the answers with Reply-To set to the visitor.
* New: The Free plan has 20 form fields; CSV export is free in every version; Stripe and PayPal payments are included in Free Plus; import from Contact Form 7.
* New: Pages with a form are kept out of page caches, and form scripts are excluded from script optimizers.
* Fixed: Network activation on Multisite, the confirmation code page on WordPress before 6.5 with PHP 8, and many smaller issues.

= 4.3.0 =

* Security: Improved form builder security and set up the i18n translation and localization infrastructure.

= 4.2.0 =

* New: Onboarding wizard, weekly email deliverability report and an Email Error Log in the dashboard widget.
* Improved: Email delivery handling, add-on installation, and form submission on cached pages.
* Security: Hardened upload, recorder, login, registration, and tracking-code handling.

Older versions: see CHANGELOG.md in the plugin folder, or the [full changelog archive](https://plugins.svn.wordpress.org/easy-form-builder/assets/changelog_old.txt).
