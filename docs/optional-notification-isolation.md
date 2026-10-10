# Optional notification failures and email continuation

Local-only change: public form flows now call the SMS provider through a shared
adapter in all six locations (forms, payments, registration, login, surveys and
replies). Only strict `true` indicates SMS success. A failed reply SMS no longer
terminates the request before reply email is attempted.

The adapter catches `Throwable` (including exceptions, missing dependencies and
TypeError), records an EFB `notification.failed` trace and returns failure so the
primary notification flow can continue. False and WP_Error returns are also
recorded by the shared optional-channel runner.

Third-party integration dispatch has an outer exception boundary, with separate
boundaries for conditional webhooks, Telegram, Google Sheets and the integration
webhook action. An action failure therefore does not skip later channels or
propagate into email dispatch. Existing anti-bot, side-effect permission and
has-work checks remain in effect. WordPress actions ignore callback return
values; returning false alone from an action already does not terminate the
request.

This handles normal failures and catchable PHP errors. Third-party `exit`/`die`,
process termination, exhausted memory and unbounded provider execution cannot
be caught by a PHP try/catch boundary; providers must not terminate requests and
must use bounded network timeouts.

Verification uses actual extracted production methods with simulated providers:

- `tests/test-optional-notification-isolation.php`: 42 assertions for success,
  false/null/zero/WP_Error, Exception/Error/TypeError, failed integration channels,
  conditional webhooks and respected channel permission vetoes.
- `tests/test-sms-failure-email-continuation.php`: 27 post-save continuation
  assertions (provider adapter tested separately above).
- Existing sender, settings and reader regressions remain available.

No real mail/SMS/webhook calls, database writes or file/data deletions occur in
these tests. This extension has not been deployed to the customer server.
