# Easy Form Builder 4.2.0: Manual Verification Checklist

This checklist records items that should be verified manually when upgrading from 4.1.3 to 4.2.0. The comparison was made against `C:\svn\easy-form-builder\tags\4.1.3`.

## Before Updating

- [ ] Take a database and files backup.
- [ ] Record the current values of the `emsfb_version` and `Emsfb_db_version` options.
- [ ] Note active Easy Form Builder add-ons and their versions.
- [ ] Confirm that the site has a working scheduled-task runner if weekly reports are expected.

## Upgrade and Database

- [ ] Update a staging copy first, if available.
- [ ] Confirm that the plugin remains active after the update.
- [ ] Confirm that `Emsfb_db_version` becomes `1.2` and that the expected tables and indexes exist.
- [ ] Open existing forms and submissions created before 4.2.0; verify that no data, settings, templates, or uploaded files are missing.
- [ ] Check the PHP error log and WordPress Site Health for migration or activation errors.

## New Setup and Email Monitoring

- [ ] On a fresh installation, complete the onboarding wizard and verify that each settings link opens the intended tab.
- [ ] Run the email delivery test with the site's real mail configuration.
- [ ] Verify that the delivery score, authentication checks, report email, and recommendations are populated correctly.
- [ ] Trigger a failed email in a controlled environment and verify that the Email Error Log records useful details without exposing secrets.
- [ ] Confirm that the weekly report is scheduled once, runs at the expected cadence, and does not send duplicate reports.

## Forms and Integrations

- [ ] Submit a form from a cached page and verify nonce/session validation, confirmation-code lookup, and the success response.
- [ ] Test payment form notification emails for both successful and failed payment states.
- [ ] Test single and multiple file uploads, file-size/type rejection, and audio/video/screen recorder uploads.
- [ ] Verify that the upload and recorder flows work with the configured spam/security add-ons and do not block genuine visitors.
- [ ] Install, update, and remove one active add-on; verify endpoint handling, subscription state, and failure messages.
- [ ] Test login and registration forms with invalid and boundary input, including password-manager autofill.
- [ ] Test conditional logic, webhooks, Google Sheets, Telegram, SMS, Stripe, and PayPal integrations that are active on the site.

## Compatibility and Release Review

- [ ] Check forms in the site's active theme and page builder, including pages affected by `wpautop`.
- [ ] Test frontend and admin flows on the supported WordPress/PHP versions used by the site.
- [ ] Confirm that translated strings, RTL layouts, email templates, and notification recipients remain correct.
- [ ] Review browser console, PHP error log, scheduled events, and email logs after the test cycle.
- [ ] Repeat the critical checks after cache purge and, where applicable, CDN or object-cache purge.

## Findings During Comparison

- Version 4.2.0 changes the database schema constant from `1.1` to `1.2` and adds an automatic schema-upgrade path. This is the highest-priority manual check for existing sites.
- The release adds or changes several email-monitoring and onboarding flows. These require real WordPress cron and mail-delivery checks; static tests cannot prove scheduling and inbox delivery.
- The release touches upload, recorder, payment, cache/session, add-on, login/registration, and security paths. Sites using those features should run the corresponding checks above before production rollout.
