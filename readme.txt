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

 Easy Form Builder is a comprehensive WordPress form plugin designed to help website owners create, manage, and maintain a wide variety of forms using a visual drag-and-drop form builder interface. The plugin is suitable for users who want to build professional forms without writing code, while still keeping full control over form data, submissions, and communication workflows, including free custom email notification templates with a built-in visual editor.

This form builder plugin can be used to create contact forms, support request forms, survey and feedback forms, payment forms (with a payment add-on such as Stripe or PayPal), subscription forms and custom login or registration forms for WordPress users. All form data is stored directly on your WordPress website, ensuring transparency, data ownership, and predictable behavior.

[youtube https://www.youtube.com/watch?v=XBVyCFP36OY]

 Easy Form Builder focuses on clarity, extensibility, and usability. The plugin does not rely on external form services by default and does not send form submissions to third-party servers. This makes it suitable for websites that require clear data handling policies and on-site form management. The few remote services the plugin can use, and when, are listed under External services.


== What Can This Form Plugin Do? ==

With Easy Form Builder, you can use a single form plugin to build and manage multiple types of forms and form-based workflows:

- Contact forms for general inquiries
- Support and feedback forms with message tracking
- Survey and poll forms with structured results
- Payment forms with Stripe, PayPal and other payment gateways (payment add-ons)
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
- Address forms in your country's layout, with a map location picker (Address & Postal add-on)
- Custom email notification templates with a built-in visual editor
- Job application forms for hiring and recruitment
- Event registration forms for conferences and workshops
- Order and booking forms with limits, schedules and stock for each option

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
The form plugin checks every answer in the browser and again on the server: required fields, formats, unique values, word limits, patterns, allowed e-mail domains, password strength, and one-time codes by e-mail or SMS. Errors are written under each field and read by screen readers.

Spam protection works without a CAPTCHA: a hidden trap field and a minimum time to fill, plus Akismet (when installed) and WordPress's own disallowed words. Spam is kept in its own tab instead of being lost. Google reCAPTCHA (v2, v2 invisible or v3) can be turned on for the whole site or per form. Cloudflare Turnstile, hCaptcha, ALTCHA, Question CAPTCHA and Friendly Captcha are add-ons (Free Plus and Pro).

= Form Limits, Scheduling and Save for Later =
Limit how many answers a form takes, open and close it on a schedule, limit answers per user, IP address, browser or e-mail address, and limit private forms to chosen user roles. Visitors can save their answers and continue later from a private link.

= Multi-Step Forms =
Multi-step forms allow long forms to be divided into multiple sections. This improves user experience and helps reduce form abandonment.

= Ready-Made Form Templates =
The plugin includes pre-built form templates to help you get started quickly. Available templates include contact forms, reservation forms, job application forms, event registration forms, quote request forms, booking forms, order forms, survey forms, and more.


== Form Entry & Message Management Dashboard ==

 Easy Form Builder includes a dedicated **Form Entry & Message Management Dashboard** that allows administrators to manage all form submissions directly from the WordPress admin area. This dashboard acts as a centralized system for reviewing messages, tracking requests, and communicating with users without relying solely on email.

Through this dashboard, administrators can read submitted messages, respond directly from the WordPress backend, and attach files to replies when needed. This approach helps keep communication organized and ensures that conversations related to form submissions remain accessible in one place.

The Form Entry & Message Management Dashboard also provides practical tools for organizing and reviewing submissions. Entries load page by page with tabs (All, Unread, Starred, Payment pending, Closed, Spam, Trash) and search. Administrators can star an entry, add internal notes, see its history, close or reopen it, move it to the trash with Undo, edit it and resend its notification (Free Plus), and print it. Form responses can be downloaded as PDF or exported to CSV with the columns you choose, or to Excel (Free Plus). Statistics per form show views, submissions and conversion (Free Plus).


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


== Payment Forms ==

 Payment gateways are add-ons, so your site only loads the gateway you use. Each add-on adds a payment field to the form builder:

- Stripe and PayPal (Free Plus and Pro), including recurring billing
- Persia Pay (ZarinPal) (Pro)
- Mollie, Payplug, Tap Payments, Omise, PayTabs, Paymob and Xendit (Free Plus and Pro)

You can use payment fields to create donation forms, order forms, and booking and service payment forms. The amount is always computed on the server. The Payments screen lists every payment with its history, and refunds it when the gateway supports refunds. Payment entries are also handled alongside other form submissions in the entries dashboard.

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
- Location picker fields (OpenStreetMap and LeafletJS, Address & Postal add-on)
- Name and address fields in each country's format
- Advanced date fields (Gregorian, Persian, Hijri and more) with date limits and time
- Repeater groups, ranking fields, hidden fields and collapsible sections
- Country, state/province, and city dropdown fields
- Multiple select dropdown fields
- Range slider fields
- Rating and star fields
- Switch and toggle fields
- Date pickers (Gregorian, Hijri, and Jalali)
- Calculation fields and number formats (Numbers & Calculations add-on)
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

- Payment add-ons: Stripe, PayPal, Persia Pay (ZarinPal), Mollie, Payplug, Tap Payments, Omise, PayTabs, Paymob and Xendit

- CAPTCHA add-ons: Cloudflare Turnstile, hCaptcha, ALTCHA, Question CAPTCHA and Friendly Captcha

- Numbers & Calculations, Typed Signature, Address & Postal, Booking and Visitor Country add-ons

- Offline forms add-on for collecting data without an active internet connection

- Hijri (Qamari) date picker add-on

- Jalali (Shamsi) date picker add-on

- Auto-Populate Integrations add-on (Autofill) for auto-filling form fields with real-time data from an external API - with API Key, Bearer Token, or Basic Auth authentication, response-to-field mapping, a built-in connection test, and smart caching

- Telegram notification add-on for sending instant form submission alerts and admin response notifications to Telegram using a bot

- Google Sheets integration for sending WordPress form submissions to Google Sheets automatically - no Zapier or third-party connector required. Connect your site with a Google service account (one-time setup, no OAuth login and no expiring tokens), let the plugin create a new spreadsheet or use one you already own, and bind each form to a sheet tab with a simple wizard. You can map form fields to spreadsheet columns with custom headers and ordering, apply ready-made sheet styles, and review every sync attempt in a built-in Google Sheet log. The header row and worksheet tab are created automatically on the first submission

- Security & Spam Protection add-on for blocking WordPress form spam without CAPTCHA - invisible, behavior-based bot detection with a hidden honeypot field, signed single-use security tokens, and per-IP rate limiting for form submissions, file uploads, confirmation-code lookups, public replies, and payment requests. It also protects your notification budget by stopping suspicious entries from consuming email, SMS, Telegram, webhook, or Google Sheets quota, supports an IP allowlist and blocklist, and keeps privacy-aware security logs that store hashed identifiers instead of raw IP addresses. It starts in Monitor-only mode, so you can review real traffic before turning on blocking

- Shield Security plugin silentCAPTCHA integration for invisible bot and spam protection without user interaction

- Easy Form Builder Data Tools, a free separate plugin: export and import forms, import from Contact Form 7 and other form plugins, and backups



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
- Payment forms with the Stripe and PayPal add-ons (Free Plus)
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

Easy Form Builder stores form entries in your own WordPress database and does not send them to WhiteStudio or to any other company. The plugin connects to the services below only for the feature named, and only when that feature is used. Form answers are never sent unless a service below says so.

**Google reCAPTCHA** (www.google.com, www.gstatic.com, or www.recaptcha.net when you choose it in Settings > Captchas)
Used only when you turn reCAPTCHA on for the site, a form, the confirmation code box, or login and registration forms. The visitor's browser loads Google's reCAPTCHA script, which sends Google the information it collects about the visit. When the form is sent, your site sends Google your secret key and the visitor's reCAPTCHA token to check it (no answers, no IP address). [Google Terms of Service](https://policies.google.com/terms), [Google Privacy Policy](https://policies.google.com/privacy).

**Google Charts** (www.gstatic.com)
Administrators only: the plugin's Panel loads the Google Charts library in your browser to draw charts of your entries. The chart data stays in your browser; Google receives the request for the library (your IP address and browser). [Google Terms of Service](https://policies.google.com/terms), [Google Privacy Policy](https://policies.google.com/privacy).

**Google Fonts** (fonts.googleapis.com, fonts.gstatic.com) and **jsDelivr** (cdn.jsdelivr.net)
Administrators only: the plugin's admin screens load the Roboto font from Google Fonts, and Settings > Response box loads the fonts you preview from Google Fonts and jsDelivr. Your browser's request (IP address and browser) reaches these services. Your visitors' pages never load Google Fonts: the response box fonts are included in the plugin. [Google Privacy Policy](https://policies.google.com/privacy), [jsDelivr Privacy Policy](https://www.jsdelivr.com/terms/privacy-policy).

**Your own font address**
If you enter a font stylesheet address of your own in Settings > Response box, your visitors' browsers load it from the address you entered. That service is your choice.

**Development copies only: jsDelivr** (cdn.jsdelivr.net) **and WhiteStudio's Iran mirror** (cdn.easyformbuilder.ir)
The release from WordPress.org includes the country, state and city lists and the poll chart library (Chart.js 4.4.1). A development copy of the plugin built without these files (for example one taken straight from GitHub) loads the lists from jsDelivr, or from cdn.easyformbuilder.ir on Persian (fa_IR) sites, and Chart.js from jsDelivr, in the visitor's browser. No form data is sent. On Persian sites the plugin also checks once an hour whether cdn.easyformbuilder.ir answers. [jsDelivr Privacy Policy](https://www.jsdelivr.com/terms/privacy-policy), [WhiteStudio Privacy Policy and Terms](https://whitestudio.team/privacy-policy-terms/).

**WhiteStudio add-on server** (whitestudio.team; easyformbuilder.ir for Persian sites)
When you install or update an add-on from Easy Form Builder > Add-ons, and when the plugin reinstalls your add-ons after it is updated. The request names the add-on and carries your site's domain and the WordPress and Easy Form Builder versions. [WhiteStudio Privacy Policy and Terms](https://whitestudio.team/privacy-policy-terms/).

**WhiteStudio license server** (whitestudio.team)
When you enter a Pro activation code, and from time to time afterwards to check that it is still valid. Sends the activation code, your site's domain, the site's admin e-mail address, the Easy Form Builder, WordPress and PHP versions, the site language and the list of active plugins. Persian (fa_IR) sites check the code on the site itself and send nothing. [WhiteStudio Privacy Policy and Terms](https://whitestudio.team/privacy-policy-terms/).

**WhiteStudio e-mail test service** (whitestudio.team, or www.whitestudio.team when the first address does not answer)
Tests whether your form e-mails arrive. What it receives and when is described in the FAQ "Does the email delivery check send data to an external service?". Form submissions are never sent to it. [WhiteStudio Privacy Policy and Terms](https://whitestudio.team/privacy-policy-terms/).

**WhiteStudio language packs** (whitestudio.team) **and WordPress.org translations** (api.wordpress.org, downloads.wordpress.org)
When your site or one of its users uses a language the plugin does not include (it includes English, Persian, Arabic and German), the plugin asks these servers for a translation of Easy Form Builder in that language and installs it in wp-content/languages/plugins. The request carries the plugin's name and version and the language codes, nothing about your forms or visitors. [WhiteStudio Privacy Policy and Terms](https://whitestudio.team/privacy-policy-terms/), [WordPress.org Privacy Policy](https://wordpress.org/about/privacy/).

**WhiteStudio feedback and review reward** (whitestudio.team)
Only when you press Send in the optional survey shown when you deactivate the plugin (your reason, your message, your e-mail address if you type it, and the plugin, WordPress and PHP versions; the service then checks your site's domain once), or when you ask for the review reward (your WordPress.org user name, your e-mail address, your site's domain and language, and the plugin version, to send the discount code). Nothing is sent if you skip them. [WhiteStudio Privacy Policy and Terms](https://whitestudio.team/privacy-policy-terms/).

**Akismet** (through the Akismet plugin)
When the Akismet plugin is installed and active, each answer is checked for spam by Akismet through that plugin. The visitor's IP address is included only as your privacy setting allows. [Akismet Terms](https://akismet.com/tos/), [Automattic Privacy Policy](https://automattic.com/privacy/).

**Webhooks you add**
A webhook rule of the Conditional Logic add-on sends the entry's answers to the address you enter, when its conditions are met.

**Add-ons**
Each add-on connects only to the service it is made for, and only after you set it up: the payment add-ons to their gateway (Stripe, PayPal, ZarinPal, Mollie, Payplug, Tap Payments, Omise, PayTabs, Paymob, Xendit), Google Sheets, Telegram, SMS through the WP SMS plugin, the CAPTCHA add-ons to their provider (Cloudflare Turnstile, hCaptcha, Friendly Captcha), Address & Postal to OpenStreetMap for address search, and Visitor Country to the IP location service you choose.


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

Recommended update. Adds an onboarding wizard and direct settings links, improves email delivery monitoring with weekly reports and an Email Error Log, strengthens form submission and add-on handling, and includes database schema updates. Back up your site before updating and verify email delivery, payment notifications, file uploads, and recorder fields after the update.


= 4.0.0 =

Major update. New form rendering engine, Gutenberg block, Elementor/WPBakery/Visual Composer widgets, email template builder, background processing, and Shield Security integration added. Database schema upgraded (v1.1). Settings API and internal functions restructured - all official add-ons will be updated to their latest versions.

**Backup your site before updating. Not backward-compatible with v3.x addons.**


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
* Changed: Fonts, the country, state and city lists and the poll chart come from the plugin, not from Google Fonts or a CDN. See External services for what the plugin still contacts.
* Changed: {field_id} of a radio button or checkbox in an e-mail subject or redirect address now gives the option's label, not its ID ({field:id.value} gives its value).
* Changed: One notice at a time on the plugin's screens; the others wait behind a "Notices" bell. Help buttons open each add-on's own guide. The admin screens meet WCAG 2.1 AA contrast.
* Changed: Add-on settings screens share the plugin's look. The Security & Spam Protection add-on is now named "Human Shield". Paymob asks the visitor for a name and mobile number when the form has none.
* Security: Answers to password fields are never stored, e-mailed or exported. Uploaded files are tied to the visitor and the field. Secret keys are write-only. The e-mail debug log moved to a private folder.
* Faster form pages: about a third as many database queries, minified scripts and styles, and the response viewer loads only where it is needed.
* Fixed: Many fixes across forms, e-mails, entries, payments, booking forms, CAPTCHA, right-to-left screens and phones. See CHANGELOG.md.
* Developers: database version 1.13 (the update runs automatically); form package, builder (efbBuilderApi), payment, CAPTCHA, notices and customer portal contracts. See CHANGELOG.md and the docs folder on GitHub.

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
