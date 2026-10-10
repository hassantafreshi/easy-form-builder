# Two customer forms: SMS failure blocked email notifications

Date: 2026-10-09. Scope: Easy Form Builder only, local and customer installation.

Affected forms:

- `/patenschaftformular-2/`: form ID 2.
- `/anmeldeformular-freiwilliger-oder-praktikantin-2/`: form ID 3.

Remote trace requests `9fa6611e` (12:13:32 UTC) and `f3dc7005`
(12:19:13 UTC) read valid settings, with sending enabled and recipients
resolved. They stop before `email.status`, `submit.dispatch` and `wp_mail`.
The submissions exist in the EFB message table at those timestamps.

Both published form definitions have `smsnoti="1"` and
`email_noti_type="msg"`; both have stored SMS configuration rows. The SMS
adapter depends on `wp_sms_send` and returns false if no provider is available
or sending fails. The active plugin list has no WP SMS provider.

In the post-save form branch, a non-true SMS result called
`wp_send_json_success` with an error response. That terminates execution before
the email block, even though the original success response has already been
flushed and the entry saved. The working contact form does not take this SMS
branch. The new JSON helper file was absent on the customer server during
diagnosis; local JSON refactoring did not cause this live failure.

Fix: preserve the SMS attempt and log `submit.sms-failed` when it is not sent,
then continue to email notifications. Do not send a second HTTP response or
terminate the already successful submission because an optional channel failed.
Only this block in `includes/class-Emsfb-public.php` was deployed.

Remote original retained at:
`.efb-sms-email-fix-backups/20261009-122628/class-Emsfb-public.php`.

Verification:

- Actual remote block reproduced the pre-email abort before patching.
- After patching, 27 source-based continuation checks pass remotely and locally,
  covering SMS enabled/disabled and true/false/null/zero results for IDs 2/3/5.
- PHP lint passes for the deployed file.
- Real notification verification uses each live form's settings and synthetic
  field values, with recipients limited to the two user-authorized addresses;
  it creates no form entries. It is separate from a full browser submission.
- Run `EFB-SMS-FIX-20261009-122907`: all four real notification calls were
  accepted by the mailer, one per form to each authorized address. Acceptance
  establishes handoff, not final inbox delivery.

Additional configuration: form ID 2 has an empty `email_to` selection, so its
normal submission does not resolve a user confirmation recipient. The admin
notification remains enabled. This intentional configuration was not changed.

No entries, files, settings or logs were deleted. No other plugin, mail server,
DNS or Plesk configuration was changed.
