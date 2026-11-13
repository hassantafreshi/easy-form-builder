=== Easy Form Builder by WhiteStudio - Drag & Drop Form Builder ===
Contributors: hassantafreshi, mostafas1990, 6arshid, aminkhadivar, presskopp, whitestudio
Donate link: https://whitestudio.team/donate-easy-form-builder/
Tags: form builder, contact form, email notification, multi step form, payment form
Requires at least: 5.7
Tested up to: 7.1
Stable tag: 4.3.1
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
Drag-and-drop WordPress forms with a built-in email delivery test, a tracking code for every entry, multi-step forms and free CSV export.

== Description ==

 Easy Form Builder is a comprehensive WordPress form plugin designed to help website owners create, manage, and maintain a wide variety of forms using a visual drag-and-drop form builder interface. The plugin is suitable for users who want to build professional forms without writing code, while still keeping full control over form data, submissions, and communication workflows, including free custom email notification templates with a built-in visual editor.

This form builder plugin can be used to create contact forms, support request forms, survey and feedback forms, payment forms using Stripe, subscription forms and custom login or registration forms for WordPress users. All form data is stored directly on your WordPress website, ensuring transparency, data ownership, and predictable behavior.

[youtube https://www.youtube.com/watch?v=XBVyCFP36OY]

 Easy Form Builder focuses on clarity, extensibility, and usability. The plugin does not rely on external form services by default and does not send form submissions to third-party servers. This makes it suitable for websites that require clear data handling policies and on-site form management.


== What Can This Form Plugin Do? ==

With Easy Form Builder, you can use a single form plugin to build and manage multiple types of forms and form-based workflows:

- Contact forms for general inquiries
- Support and feedback forms with message tracking
- Survey and poll forms with structured results
- Stripe payment forms for online payments
- PayPal payment forms for collecting online payments
- Login (sign-in) forms for WordPress users
- Registration (sign-up) forms for new users
- File upload forms
- Audio recording forms for collecting voice messages and voice feedback
- Video recording forms for video testimonials and webcam responses
- Screen recording forms for bug reports and support requests
- Private or restricted-access forms
- Multi-step forms for improved usability
- Smart forms with conditional logic that show or hide fields based on user answers
- Electronic signature forms for contracts and agreements
- Location picker forms for address and map selection
- Custom email notification templates with a built-in visual editor
- Job application forms for hiring and recruitment
- Event registration forms for conferences and workshops
- Order forms with recurring and subscription billing options

The form builder provides a consistent interface for form creation, submission handling, and response management inside WordPress.


== Core Features ==

= Drag & Drop Form Builder =
The plugin includes a visual drag-and-drop form builder. Fields can be added, removed, and reordered without writing code. Each field provides configuration options such as labels, placeholders, validation rules, and display settings.

= Email Notification =
Easy Form Builder supports automatic email notifications after a form is submitted. Notifications can be sent to administrators, form managers, or users. Email templates can be customized to control the message content and layout.

Custom email notification templates are included for free. The built-in visual editor lets you design clear, branded WordPress form notification emails without writing HTML code. You can customize the email subject, message body, layout, and submission details so each contact form, survey form, payment form, registration form, or support form can send a professional email notification that matches your website and workflow.

To help you keep WordPress form notifications reliable, Easy Form Builder includes an email delivery check ("Check Email Server") that sends a real test message the same way a form email is sent, waits for it to arrive, and reports a deliverability score out of 100. A detailed report with spam score, SPF, DKIM and DMARC results and recommendations is emailed to the site admin. The Easy Form Builder dashboard widget also shows useful form activity insights, including page views for pages containing forms, completed form submissions, sent email counts, failed email counts, and an **Email Error Log** button where you can review any email sending errors when needed.

For detailed setup instructions, see:
[How to set up form notification emails in Easy Form Builder](https://whitestudio.team/document/how-to-set-up-form-notification-emails-in-easy-form-builder/)

= Confirmation Code (Tracking ID) =
Each form submission generates a unique confirmation code. This tracking ID allows administrators and users to reference, search, and manage individual submissions efficiently.

Learn more about this feature:
[How to activate a Confirmation Code in Easy Form Builder](https://whitestudio.team/document/how-to-activate-confirmation-code-in-easy-form-builder/)

= Form Validation and Spam Protection =
The form plugin includes client-side and server-side validation. Google reCAPTCHA v2 can be enabled to reduce spam and automated submissions.

= Multi-Step Forms =
Multi-step forms allow long forms to be divided into multiple sections. This improves user experience and helps reduce form abandonment.

= Ready-Made Form Templates =
The plugin includes pre-built form templates to help you get started quickly. Available templates include contact forms, reservation forms, job application forms, event registration forms, quote request forms, booking forms, order forms, survey forms, and more.


== Form Entry & Message Management Dashboard ==

 Easy Form Builder includes a dedicated **Form Entry & Message Management Dashboard** that allows administrators to manage all form submissions directly from the WordPress admin area. This dashboard acts as a centralized system for reviewing messages, tracking requests, and communicating with users without relying solely on email.

Through this dashboard, administrators can read submitted messages, respond directly from the WordPress backend, and attach files to replies when needed. This approach helps keep communication organized and ensures that conversations related to form submissions remain accessible in one place.

The Form Entry & Message Management Dashboard also provides practical tools for organizing and reviewing submissions. Administrators can change the status of messages (Open or Closed), edit or delete entries, search submissions by form or confirmation code, and receive notifications when new messages arrive. Form responses and message data can be downloaded in PDF format or exported to CSV files for reporting, archiving, or further analysis.


== Survey and Poll Forms ==

Easy Form Builder includes dedicated fields for creating survey and poll forms. These fields allow website owners to collect structured feedback and analyze results directly inside WordPress.

Survey-related features include:

- Rating star fields
- 5-point scale fields
- Net Promoter Score (NPS) fields
- Yes / No question fields
- Visual charts for survey results
- Exporting survey responses to CSV format

Survey forms can be used for customer feedback, research, evaluations, and internal assessments.


== Conditional Logic - Show or Hide Fields Based on User Answers ==

Easy Form Builder includes a visual conditional logic builder that turns static forms into smart forms. Conditional logic (also known as conditional fields) lets a form react to what a visitor enters, so each user only sees the fields that are relevant to them. Shorter, more personal forms reduce form abandonment and improve completion rates.

With conditional logic you can:

- Show or hide fields based on a user's answer
- Show or hide entire steps in multi-step forms
- Make fields required or optional dynamically
- Set, copy, or clear field values automatically
- Jump to a specific step (skip logic and conditional branching)
- Send conditional email notifications to different recipients based on the submitted answers

Conditions support text, choice, numeric, and date comparisons (is, is not, contains, greater than, less than, between, before, after) and can be combined with AND/OR groups. Rules are enforced on the server as well as in the browser, so hidden or disabled fields are validated and sanitized correctly - conditional logic is not just a front-end effect.

Conditional logic is available at no cost in the Free Plus version: up to 3 logic rules per form, up to 2 conditions per rule, and up to 2 conditional email notification rules, with an in-builder Test mode. The Pro version removes these limits and adds nested AND/OR groups, calculations, rule priority, conditional thank-you messages and redirects, conditional webhooks, rule export/import, and the logic Inspector (debugger).


== Payment Forms with Stripe ==

 Easy Form Builder supports Stripe payment fields, allowing you to collect online payments securely through your WordPress forms.

You can use Stripe payment fields to create:

- Donation forms
- Order forms
- Booking and service payment forms

Payment entries are handled alongside other form submissions and can be reviewed through the form management dashboard.

For a step-by-step guide, see:
[How to Create a payment form to collect online payments in Easy Form Builder](https://whitestudio.team/document/how-to-create-a-payment-form-in-easy-form-builder/)


== Login and Registration Forms ==

The form builder allows you to create custom login and registration forms for WordPress users. These forms can replace the default WordPress login page and provide a customized user onboarding experience.

Login and registration forms integrate with WordPress user management and allow you to control form layout, validation, and additional user fields.

== Advanced Form Fields ==

 Easy Form Builder provides a wide collection of advanced form fields, including:

- Drag-and-drop file upload fields
- Audio recorder field for recording voice messages from the microphone
- Video recorder field for recording video from the webcam
- Screen recorder field for capturing on-screen recordings
- Signature (e-signature) fields
- Location picker fields (OpenStreetMap and LeafletJS)
- Country, state/province, and city dropdown fields
- Multiple select dropdown fields
- Range slider fields
- Rating and star fields
- Switch and toggle fields
- Date pickers (Gregorian, Hijri, and Jalali)
- Telephone and mobile input fields
- Color picker fields
- Matrix and table fields for structured data collection

These fields allow you to build complex, region-aware, and structured forms without custom development.


== Audio, Video, and Screen Recording Form Fields ==

Easy Form Builder lets you add audio recording, video recording, and screen recording fields to any WordPress form, so visitors can record and submit media right from the browser - no extra apps, plugins, or third-party recording services. Every recording is uploaded to your own WordPress site, so your form data stays under your control.

- Audio recorder field - let visitors record a voice message from their microphone. Great for voice feedback forms, audio testimonial forms, voice message and voicemail forms, language and pronunciation practice, and podcast or audio submission forms.
- Video recorder field - let visitors record video from their webcam. Perfect for video testimonial forms, job application video introductions, customer video feedback, and video question-and-answer forms.
- Screen recorder field - let visitors record their screen (with optional microphone audio). Ideal for bug report forms, technical support request forms, product feedback, and tutorial or demo submission forms.



== Integrations and Add-ons ==

 Easy Form Builder supports optional integrations and add-ons that extend the functionality of the form plugin:

- Conditional Logic add-on for building smart forms - show or hide fields and steps, send conditional email notifications, and (in Pro) trigger conditional redirects and webhooks based on user answers. Included at no cost with the Free Plus version

- SMS notification add-on for sending text messages on form submission

- Stripe add-on for extended payment workflows

- PayPal add-on for creating payment forms and managing transactions through PayPal

- Offline forms add-on for collecting data without an active internet connection

- Hijri (Qamari) date picker add-on

- Jalali (Shamsi) date picker add-on

- Auto-Populate Integrations add-on (Autofill) for auto-filling form fields with real-time data from an external API - with API Key, Bearer Token, or Basic Auth authentication, response-to-field mapping, a built-in connection test, and smart caching

- Telegram notification add-on for sending instant form submission alerts and admin response notifications to Telegram using a bot

- Google Sheets integration for sending WordPress form submissions to Google Sheets automatically - no Zapier or third-party connector required. Connect your site with a Google service account (one-time setup, no OAuth login and no expiring tokens), let the plugin create a new spreadsheet or use one you already own, and bind each form to a sheet tab with a simple wizard. You can map form fields to spreadsheet columns with custom headers and ordering, apply ready-made sheet styles, and review every sync attempt in a built-in Google Sheet log. The header row and worksheet tab are created automatically on the first submission

- Security & Spam Protection add-on for blocking WordPress form spam without CAPTCHA - invisible, behavior-based bot detection with a hidden honeypot field, signed single-use security tokens, and per-IP rate limiting for form submissions, file uploads, confirmation-code lookups, public replies, and payment requests. It also protects your notification budget by stopping suspicious entries from consuming email, SMS, Telegram, webhook, or Google Sheets quota, supports an IP allowlist and blocklist, and keeps privacy-aware security logs that store hashed identifiers instead of raw IP addresses. It starts in Monitor-only mode, so you can review real traffic before turning on blocking

- Shield Security plugin silentCAPTCHA integration for invisible bot and spam protection without user interaction



== Premium Features Included Free ==

Many WordPress form plugins only offer these features in a paid plan. In Easy Form Builder, they are included at no cost:

- Form entries stored in your own WordPress database, with a full entry management dashboard (Free)
- Email notifications after each form submission (Free)
- Custom email notification templates with a built-in visual editor for branded WordPress form emails (Free)
- File upload forms (Free)
- Confirmation code (tracking ID) for every submission (Free)
- Multi-step forms (Free)
- Basic survey and poll fields (Free)
- Exporting form entries to CSV (Free)
- Conditional logic to show or hide fields based on answers (Free Plus)
- Payment forms with Stripe and PayPal (Free Plus)
- Advanced fields such as signature, location picker, matrix and table, and more (Free Plus)
- Downloading responses as PDF (Free Plus)
- Automated weekly email deliverability test with spam score and SPF/DKIM/DMARC checks (Free)

The Free Plus version is completely free of charge - the only difference is a small "Powered by Easy Form Builder" link and credit, included only in the content of the page where the form is published.


== Available Versions ==

 [Easy Form Builder is available in multiple versions](https://whitestudio.team/document/easy-form-builder-free-plus-activation-guide/) to support different usage needs. Each version is designed to provide a clear and predictable set of features.


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
No. All form submissions and messages are stored locally on your WordPress website. Easy Form Builder does not send form data to external servers by default.

= Can I move my forms from Contact Form 7? =

Yes. If Contact Form 7 is installed, the Create and Panel screens show "Import from Contact Form 7". Choose the forms and each one becomes a new Easy Form Builder form with its fields, choices, notification addresses and messages. A report lists anything that could not be carried over (for example quiz questions or hidden fields) and what to do instead. Your Contact Form 7 forms stay as they are, and you can let the new form replace the old one on your pages.

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
Yes. Stripe payment fields are supported.
[How to Create a payment form to collect online payments in Easy Form Builder](https://whitestudio.team/document/how-to-create-a-payment-form-in-easy-form-builder/)

= Does Easy Form Builder support PayPal payments? =
Yes. You can add and set up the PayPal payment field to accept online payments through your WordPress forms. The PayPal add-on supports one-time payments as well as recurring billing options including daily, weekly, monthly, and yearly subscriptions. Payment entries are managed alongside other form submissions in the Form Entry & Message Management Dashboard.

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
The Free Plus version extends the Free version by enabling advanced form fields and features available in the form builder. It also unlocks conditional logic at no cost - up to 3 logic rules per form, up to 2 conditions per rule, and up to 2 conditional email notification rules - plus payment forms with Stripe and PayPal. CSV export and the weekly email deliverability report are included in every version.

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
Yes. The country, state/province, and city lists used in Easy Form Builder are automatically loaded from an external CDN-hosted JSON source maintained on GitHub.

These lists are fetched from the following public repository:
https://github.com/hassantafreshi/Json-List-of-countries-states-and-cities-in-the-world

The data is delivered via a CDN to ensure fast loading and reliable access, and it includes country names, states/provinces, and cities in multiple language formats where available.

If you notice any missing entries, incorrect names, or data issues related to countries, states, or cities, please report the issue by creating a ticket in the GitHub repository above. This helps keep the dataset accurate and up to date for all users.

No personal or form submission data is sent to this external source. The CDN is used only to retrieve static geographic lists required for form fields.

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

= 4.3.1 =

Recommended update. Turns form emails on if they were off (you now receive each submission by email), verifies Stripe payments on the server and keeps form pages out of page caches. Purge your page cache and CDN after updating. Requires WordPress 5.7+ and PHP 7.4+.

= 4.3.0 =

Mandatory update. Improves form builder security and sets up the i18n translation and localization infrastructure. Back up your site before updating.

= 4.2.0 =

Recommended update. Adds an onboarding wizard and direct settings links, improves email delivery monitoring with weekly reports and an Email Error Log, strengthens form submission and add-on handling, and includes database schema updates. Back up your site before updating and verify email delivery, payment notifications, file uploads, and recorder fields after the update.


= 4.0.0 =

Major update. New form rendering engine, Gutenberg block, Elementor/WPBakery/Visual Composer widgets, email template builder, background processing, and Shield Security integration added. Database schema upgraded (v1.1). Settings API and internal functions restructured - all official add-ons will be updated to their latest versions.

**Backup your site before updating. Not backward-compatible with v3.x addons.**


== Changelog ==

= Unreleased =

* New: Payment framework for new gateway add-ons (Mollie, Payplug, Tap, Omise, PayTabs, Paymob, Xendit, coming as separate add-ons). The amount is always computed on the server (option prices, price fields, computed prices, a fixed amount and donations with suggested amounts); a payment is recorded only after the gateway confirms it, once, whether the confirmation comes from the gateway's webhook, the visitor's return or the background check.
* New: A customizable payment field: button colour, text and icon, the order of its parts, the summary, payment methods, texts per language, phone layout (Pro). A banner tells every visitor when a gateway is in test mode.
* New: Easy Form Builder → Payments: every payment with filters and totals, its event history, re-check, refund (when the gateway supports it), and the gateway settings. Keys are stored encrypted and never shown again.
* New: For payment forms, choose when each notification goes out (after payment, when the form is sent, or both); an entry waits as "pending payment" until the payment is confirmed.
* Fixed: The builder preview of a multi-step PayPal payment form stopped with an error and its buttons did not work.
* New: Mollie add-on (iDEAL | Wero, Bancontact, cards, PayPal and more) on the new payment framework: Mollie's hosted payment page, every payment checked with Mollie before it counts, refunds from the Payments screen, separate Test and Live keys.
* New: Payplug add-on (Cartes Bancaires, Visa, Mastercard, Bancontact, Oney instalments and more, euro only) on the new payment framework: Payplug's payment page or card fields inside the form with a choice of card brand, every payment checked with Payplug before it counts, refunds from the Payments screen, separate Test and Live keys.
* New: Tap Payments add-on (Gulf: mada, KNET, BENEFIT, cards and the methods of Tap's hosted page) on the payment framework; amounts in KWD, BHD and OMR with three decimals, refunds from the Payments screen.
* New: Omise add-on (Thailand: PromptPay) on the payment framework: the visitor scans a PromptPay QR code inside the form, with a countdown, "Save QR image" and "Get a new QR code"; every payment checked with Omise before it counts, signed webhooks, separate Test and Live keys.
* New: PayTabs add-on (Saudi Arabia, UAE, Egypt, Oman, Jordan, Iraq and global: cards, mada, STC Pay, Apple Pay, KNET, Meeza QR, valU and more) on the payment framework: the PayTabs page as a redirect or inside the form, the account region per Test and Live, signed notifications checked with PayTabs, refunds, three-decimal currencies.
* New: Paymob add-on (Egypt, Saudi Arabia, UAE, Oman, Pakistan: cards, Meeza, mobile wallets, kiosk, valU, mada, STC Pay, Apple Pay and more) on the payment framework: Paymob's Unified Checkout with the methods you map to your integration IDs, callbacks checked with your HMAC secret, refunds from the Payments screen, separate Test and Live keys.
* New: Xendit add-on (Indonesia, the Philippines, Thailand, Vietnam, Malaysia, Singapore: QRIS, GCash, Maya, OVO, DANA, virtual accounts, cards and more) on the payment framework: Xendit's hosted payment page with the channels you choose, every payment checked with Xendit before it counts, refunds from the Payments screen, separate Test and Live keys.
* Improved: Payment forms explain every problem: a field the gateway needs (for example the payer's e-mail for Tap, the wallet number for Paymob, the mobile number and address for Oney) is marked required and the message appears next to that field; declined, cancelled, expired or 3-D Secure payments, insufficient funds and an unreachable gateway each have their own message with what to do next; the builder shows red the problems that stop a form from taking payments (no keys, wrong currency, two payment fields …) and the gateway settings tell you when the gateway refused your keys or its webhooks.
* Improved: Tap's payment page opens in Arabic for Arabic visitors.
* Improved: The payer's e-mail sent to a gateway is the field you chose in the payment field or the form's confirmation e-mail field, never another e-mail field of the form. Mollie can take a payer e-mail (it sends bank transfer instructions there) and no longer lists methods it cannot start from a form (Klarna, in3, Riverty, Billie, Billink, Alma, vouchers).
* Fixed: Payplug Oney instalments follow Payplug's documented rules: Italian addresses are accepted and Monaco is no longer offered, the payment is sent as an authorization that is captured once Oney accepts, with the cart and shipping details Oney requires, and an e-mail address with "+" (which Oney refuses) is caught at the field before anything is sent. While Oney reviews a request, the visitor is told so.
* Improved: A payment form that cannot take payments yet (no gateway keys, the gateway add-on switched off, a currency the gateway does not take, a payer field the gateway needs …) still saves, but visitors see "Online payment for this form is not available right now" instead of the payment field, its Send button is disabled and nothing is stored. You see what is missing with a link to fix it: in the "Saved" dialog, on the form's page while logged in, as a "Payment setup incomplete" badge in the forms list and in a notice on Easy Form Builder's screens. Adding the keys later makes the form ready without saving it again.
* Developers: database version 1.5 (payment tables); hooks efb_register_payment_gateways, efb_payment_paid, efb_payment_status_changed and more; see docs/payments/PAY00-payment-framework.fa.md.
* New: Limit how many answers a form takes; when it is full visitors see your "full" message instead of the form, and deleting an answer frees its place. Optionally show "N places left". Two visitors can never take the last place, and a payment that fails or expires gives its place back.
* New: Open and close a form on a schedule (site time) with "not open yet" and "closed" messages; a page opened just before closing can still send for the minutes you allow.
* New: Limit answers per logged-in user, IP address, browser or e-mail address, ever or per day, week, month or year (Free Plus). The IP limit follows the form's privacy setting (anonymized, or not applied when the form stores no IP).
* New: Private forms can be limited to chosen user roles; administrators always pass.
* New: "Save and continue later": visitors save their answers and get a private link (copy or e-mail) that works for the days you choose; passwords, files, signatures and payment details are never saved (Pro).
* New: Partial answers: with the visitor's consent, answers left before sending appear in a "Partial" list with progress and a masked contact (Pro).
* New: Custom CSS for one form, applied only to that form and cleaned of anything that could load content from elsewhere.
* New: Show a form in a popup: [EMS_Form_Builder id=5 popup="1" popup_label="Register"] or the block's "Show as a popup" (Free Plus).
* Fixed: A shortcode with an extra attribute (for example popup="1") showed the wrong form.
* Developers: database version 1.6 (counter and draft tables); form keys lim_*, sched_*, allowed_roles, custom_css, draft_*, partial_*; REST forms/gate, forms/draft/*, forms/partial/save; efbBuilderApi.setFormSettings() and formGate(); see docs/autopilot-integration.md (F11).
* New: Accept each value only once (for example one registration per e-mail address or ID number) in a form; answers sent before you switch it on are checked too. Two visitors can never both use the same value, a payment that fails gives the value back, and deleting an answer frees it (Free Plus).
* New: Word limits, a maximum length for text areas and a live counter ("88 characters left") that screen readers hear only near the limit; Persian and Arabic digits in the counter.
* New: A custom pattern (regular expression) for text fields, checked as you type it in the builder with a test box; patterns that could freeze a page are refused (Free Plus).
* New: Allow or block e-mail domains (example.com, *.edu, name@example.com) (Free Plus).
* New: Read-only fields, prefix and suffix ("+98", "kg"), and the description above the field, while the field is in focus, or behind a "?" help button.
* New: Passwords: ask to type the password twice, a strength meter, a minimum strength (Free Plus) and a show-password button.
* New: Your own error message for each rule (Free Plus); the server now answers with the field's own "required" message too.
* Fixed: An e-mail or website field whose maximum length was left empty refused every answer on the server.
* Fixed: A text area counted each line break as three characters, and a too-short text field did not stop Next in the browser.
* Developers: database version 1.7 (unique-value table); field keys unique, wmin, wmax, counter, pattern_b64, email_domain_mode, email_domains, readonly, prefix, suffix, message_place, pw_*, msg_*; efbBuilderApi.setFieldRules(); error codes efb_field_<rule>; see docs/autopilot-integration.md (F03).

* برای یکپارچگی با AutoPilot (for the AI Site Assistant AutoPilot integration, API version 1):
* New: A builder API for assistants and integrations, window.efbBuilderApi: read, replace and insert fields, field templates without the canvas, a render that resolves when the canvas is really drawn, refresh of the open settings panel, and events for every change (efb:form-changed, efb:canvas-rendered). PHP: get_efbFunction()->field_templates_efb() and capabilities_efb(), efb_logic_validate_rules(), efb_plan_efb() and efb_plan_at_least_efb(). See docs/autopilot-integration.md.
* New: One plan and add-on registry (efb_var.capabilities) used by the builder's own locks, so a lock and its upgrade message always name the same plan.
* New: Choosing "Login" or "Register" as the form type adds the fields those forms need; Register is available again; Save refuses a login form without them.
* Improved: The SMS switch of a mobile field also turns on the form's SMS, fills the default messages and checks admin phone numbers on the server.
* Improved: A price field computed by a conditional-logic rule (for example $5 per guest) is shown in the total and charged as the server computes it.
* Improved: Installing an add-on updates the page without an immediate reload when an integration asks for it; the install answer no longer contains secret keys.
* Fixed: The state list stopped following the country after a form was saved.
* Fixed: A redirect address was shown with one slash and could switch the form back to "show a message" when saved again.
* Fixed: The redirect, sender email, weekly report, colours and reply attachments showed "Pro only" although Free Plus includes them; SMS and Telegram showed two messages.
* Fixed: Saving a form with SMS or Telegram switched on failed when that add-on was not active; it is now saved with the notification off and a warning.
* Fixed: "window.intlTelInput is not a function" on pages with a mobile field behind a cache plugin that delays scripts.
* Fixed: The settings screen could store "NotFoundEl" as a value.
* New (privacy): Settings > Privacy: store visitors' IP addresses and browsers, store them anonymized, or not at all (per form too); clear the addresses already stored.
* New (privacy): Delete or anonymize entries automatically N days after their last activity, with their replies and uploaded files; a preview says how many entries the next run removes.
* New (privacy): "Do not store entries, send notifications only" for contact and subscribe forms.
* New (privacy): Tools > Export Personal Data and Erase Personal Data include form entries; a suggested privacy policy text is added to the Privacy Policy Guide.
* Fixed: Deleting an entry or a form left its replies behind, and no delete removed the uploaded files; uploads never claimed by an entry were never cleaned up.
* Security: The visitor's IP address is taken from forwarding headers only when the request comes through a trusted proxy (filter efb_trusted_proxies), so it cannot be chosen by the visitor; debug logs no longer contain full IP addresses; the CSV export rows are no longer kept in the browser's storage.
* New: Payment gateway add-ons can add their own options to the payment field (for example which form fields hold the payer's name, e-mail or billing address), their own button text and look, a logo and amount limits per payment method, and warnings when a form is saved (for example "only accepts EUR"). The builder shows these options and warnings; payment methods can be chosen and ordered in the field.
* New: "Try again" and "Get a new QR code" start a new payment for the same entry instead of sending the form again. The page keeps checking a pending payment as long as the gateway allows (up to 24 hours).
* New: Payments screen: "Mark as refunded outside EFB" (with a reason, recorded in the payment's history), refunds that the gateway only accepted are shown as waiting and count once confirmed, and payment notices and extra buttons per gateway on its settings card; "Test connection" shows the gateway's own message.
* New: A second webhook address (/?efb_pay_webhook=…) for hosts that block the WordPress REST API for visitors.
* Fixed: A payment refunded at the gateway before the site saw it paid stayed "pending" forever; a gateway answer about another payment made the gateway resend its webhook for a day; the payment method of a pending payment was not saved.
* Changed: The payment framework ships no gateway: the Payments menu and the payment field appear once a gateway add-on is active.

= 4.3.1 =

* Improved: Forms use your theme's font; typed Jalali and Hijri dates are checked; offline answers come back after a reload; fixes for Stripe, PayPal, ZarinPal, SMS, Telegram, Google Sheets, Conditional Logic, Auto-Populate and the date pickers.

* Security: Stripe one-time payments are now verified with Stripe on the server before the form is submitted. A confirmation that fails for a moment after the card was charged is retried and the form is still submitted, and pending payments are re-checked in the background; an attempt that was declined or abandoned is marked Failed after an hour.

* Changed: Form emails are on by default. Updating from an older version turns "This site can send emails" on once if it was off (sites set up before 4.3.1 started with it off, and a failed email check used to switch it off). If you had turned it off on purpose, turn it off again in Panel > Settings > Email; it stays off. An email check no longer turns form emails off.

* Changed: The owner's notification email now includes the submitted answers and has Reply-To set to the visitor's address. The visitor's confirmation email keeps only the confirmation code and link unless the form chooses otherwise.

* Security: Fixed a stored cross-site scripting issue through the Signature field. A submitted signature must now be a complete, valid PNG image; it is stored as a re-encoded copy under the field's saved type, and every value shown in the response viewer is escaped, including responses saved by earlier versions.

* Security: Hardened every path that stores or shows visitor data. Each saved answer keeps the field's own label and type from the form, whatever the browser sent. Replies on the confirmation-code page are rebuilt from an allow-list (attachments must be uploads on this site); register forms no longer store the password or rows for fields the form does not have; notification emails are built from the validated answers and send the visitor copy to one verified address; the page link must be on this site's own domain; the confirmation-code finder escapes the code from the address bar; the response viewer only links http(s) attachments and ignores invalid colors, ratings and currencies; passwords saved by earlier versions are masked.

* Fixed: Saving the settings replaced the Stripe and PayPal secret keys with an internal placeholder, so payments then failed (Stripe answered 401). Secret keys are now saved as typed, and a placeholder is never stored or used as a key.

* Fixed: A double quote typed by a visitor showed as two apostrophes ('') in the response viewer, the entries list, the CSV export and the notification email. It now shows as a double quote, also in entries saved by earlier versions.

* Changed: In the CSV export a Signature field is a link to the signature image instead of the image data, which was thousands of characters long and could exceed a spreadsheet cell's limit. The link opens only for a logged-in user who may read responses; the signature is not made public.

* Fixed: Ticking the reCAPTCHA on the confirmation-code page sent a request the server refused, which logged a false "request failure". "Enable Google reCAPTCHA in the response box" can now be switched off after the site key was removed, and the page shows no reCAPTCHA without a site key (it used to block every lookup).

* Security: The Google reCAPTCHA on the confirmation-code page is now verified on the server for every lookup; before, it was checked only in the browser and could be skipped. Sites that turned it on (Settings > "Enable Google reCAPTCHA in the response box") need the secret key saved as well.

* Security: Uploaded archives must really be the archive type they are named as, and files containing a PHP open tag are refused.

* New: The Free plan has 20 form fields: terms and conditions, country list, star rating and heading joined the 16. Forms that already use them keep working on every plan.

* New: CSV export of form entries is free in every version.

* New: Stripe and PayPal payments are included in Free Plus.

* New: Import from Contact Form 7. The Create and Panel screens offer to copy your Contact Form 7 forms into Easy Form Builder in one click: fields, choices, required fields, notification addresses, the visitor confirmation email and the message after sending. A report for each form says what was carried over and what could not be, and why. Your Contact Form 7 forms are not changed or deleted. Optionally the new form replaces the old one on your pages; each page is saved as a revision first, so the change can be undone. The importer loads only on these screens.

* Improved: Only Persian sites check the Iran CDN; other sites no longer contact it.

* Fixed: Email addresses typed with Persian or Arabic digits, invisible direction marks, a fullwidth "@" or a Persian decimal separator were rejected as not valid (in forms, registration, login and password recovery).

* Fixed: The email check reported "not received" when the test message took more than 45 seconds to arrive (common with Microsoft 365 and hosts that queue mail). It now waits until the message arrives or the test expires.

* Fixed: The email check forced its own sender address over the one an SMTP plugin sets, so it failed on Microsoft 365 while form emails arrived. It now sends exactly like a form email.

* Fixed: A new form was missing from the Easy Form Builder block for up to an hour.

* Fixed: On phones, the field category tabs were cut off and Form Settings and Help were hidden in a sideways-scrolling toolbar.

* Fixed: Saving a form said "updated" even when the database refused the change or the form no longer existed, and the builder then discarded its local draft. A save that is not stored now says so, and the draft is kept.

* Fixed: Where Google Fonts, jsDelivr or gstatic.com hang instead of failing (common on some networks, for example in Iran), Easy Form Builder admin screens stayed blank or on "Please wait". These files no longer hold up the page.

* Fixed: The heading field's font size control was labelled "Height".

* Fixed: The admin email shortcode in email templates (shortcode_admin_email, shown in the default footer) now uses the site's Administration Email Address from Settings > General instead of the first user's address.

* Fixed: In Email Settings, the descriptions under the two weekly report switches were swapped.

* Fixed: A form holding a field the site's plan does not include (for example a signature after moving from Pro to Free) showed the plan notice but pushed the following fields and the Submit button outside the form. The rest of the form now works.

* Fixed: An entry with malformed data could stop the owner's notification email with a server error.

* New: Pages that show a form in their content, and the tracking and payment views, are kept out of page caches (LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache, WP-Optimize, Cache Enabler, SiteGround, Cloudflare and others). Pages with a form only in a sidebar, footer or widget stay cached. Developers can adjust this with the `emsfb_cache_bypass` filter.

* New: Form scripts are excluded from script optimizers (delay/defer, combine and minify JavaScript options) and wait for jQuery when it loads late, so forms also work with Cloudflare Rocket Loader.

* New: Each form gets a short-lived session before it is submitted. When a firewall, a REST API restriction or a rate limit blocks that step, the form falls back to the 4.3.0 request, which the server still fully validates.

* New: A dashboard notice reports form requests that fail on the server. It can be dismissed and shows details only on Easy Form Builder screens.

* Fixed: Network activation on WordPress Multisite caused a fatal error on admin pages, and sub-sites never got their database tables. Each sub-site now gets them the first time its dashboard opens.

* Fixed: The confirmation code (tracking) page caused a fatal error on WordPress versions before 6.5 with PHP 8.

* Fixed: Custom dashboard texts saved by version 3.x stopped the settings from saving.

* Fixed: Apostrophes inside email template styles (for example font names) were broken when the template was saved.

* Fixed: Form submissions and replies containing double quotes failed.

* Improved: Reinstalling add-ons after an update is time-limited, so admin pages no longer wait on an add-on server that does not answer.

* Improved: The email test report warns about active page caching plugins, and SiteGround and Swift Performance Lite are detected.

* Improved: Payment requests with malformed values get a clear error message instead of a server error.

* Improved: When a submission is refused (for example by Form Security & Spam Protection in strict mode or a rate limit), visitors see the actual reason instead of a generic error.

* Changed: Requires WordPress 5.7 or later and PHP 7.4 or later.

= 4.3.0 =

* Security: Improved form builder security and set up the i18n translation and localization infrastructure.

= 4.2.0 =

* New: Added an onboarding wizard for new installations, with setup progress and direct links to the relevant settings tabs.

* New: Added a weekly email deliverability report with delivery status, score, authentication checks, and recommendations.

* New: Added an Email Error Log and expanded email activity details in the Easy Form Builder dashboard widget.

* Improved: Improved email delivery handling, error reporting, and payment form notification reliability.

* Improved: Improved add-on installation and endpoint handling, including more resilient download and subscription flows.

* Improved: Improved form submission handling on cached pages and strengthened session, nonce, and recovery-token validation.

* Security: Hardened upload, recorder, login, registration, and tracking-code handling with additional validation and abuse protections.

* Changed: Updated the database schema to version 1.2. Existing installations run the schema upgrade automatically when the plugin loads.


= 4.1.3 =

* New: File upload form fields now have a built-in limit (3 files per field, no add-on needed), your own budget/size settings, server-side file-type enforcement, and fixed audio/video/screen recording uploads with clearer error messages.

* New: Setup guide for new installs, with direct links to each settings tab.

* New: Weekly email delivery report for your forms.

* Fixed: Stray empty paragraphs no longer appear between fields on themes/page builders that run wpautop after shortcodes.

* Fixed: Payment form notification emails could fail to send.

* Fixed: Forms keep working on cached pages instead of showing a security error.

* Improved: Faster page loads - forms no longer wait on a remote check to render.

= 4.1.2 =

* Fixed: Forms with file upload or recorder fields no longer get wrongly blocked by Form Security & Spam Protection in WordPress Form Builder.

* Fixed: Genuine visitors are no longer blocked when their browser or password manager auto-fills a hidden field.

* New: Audio, video, and screen recording fields released for WordPress Form Builder.

* Improved: Smarter spam scoring in Form Builder for recorder and file-upload forms, so real visitors aren't mistaken for bots.

* Fixed: Password field placeholder now displays correctly.

* Improved: Safe plugin loading - if any plugin file is missing after an update or an incomplete deploy, the plugin now stays inactive for that request and shows an admin notice asking for a reinstall, instead of causing a fatal error on the whole site.


= 4.1.1 =

* Fixed: Restored plugin files that were missing from the 4.1.0 release package on wordpress.org, which caused a fatal error after updating.

= 4.1.0 =

* New: Visual Conditional Logic Builder - build smart forms without code. Show or hide fields and steps, set/copy/clear values, jump to a step, dynamically change required/optional, run basic calculations, and combine conditions with numeric/date operators and nested AND/OR groups.

* New: Conditional workflows - route submissions with conditional email notifications, show a different thank-you message or redirect, and fire webhooks only when your conditions are met. Includes an in-builder Test mode and Inspector.

* New: Automated email deliverability monitor - sends a real test email through your server and verifies actual delivery (not just "sent") on activation, after each update, and weekly.

* New: AI-analyzed email deliverability report - the test email is inspected on the WhiteStudio server and analyzed with AI to produce a plain-language diagnosis, including a deliverability score, spam-score analysis (SpamAssassin), SPF/DKIM/DMARC authentication checks, delivery details, likely causes, and actionable recommendations, delivered to your admin email.

* New: One-click email test with live step-by-step progress and an instant deliverability score and grade, plus a weekly form activity digest (forms, page views, submissions, emails sent/failed).

* New: Multiple file uploads with improved upload progress feedback.

* New: Form builder autosave - never lose your work while designing a form.

* Improved: Session management and nonce refresh so forms served from cached pages stay valid and submittable.

* Improved: Smarter add-on management with automatic CDN failover and more resilient add-on downloads (retry, resume, and subscription handling).

* Security: Per-IP throttling for tracking-code lookups, an expanded list of blocked upload file extensions, a capability check for form preview, and hardened Google reCAPTCHA verification.

* Fixed: Guard against fatal errors on hosts that disable certain PHP functions.

* Tested up to WordPress 7.0.2


Looking for even older release notes? See the [full changelog archive](https://plugins.svn.wordpress.org/easy-form-builder/assets/changelog_old.txt).
