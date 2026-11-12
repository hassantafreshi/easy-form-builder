# End-to-end tests on a real WordPress

`run.js` drives Easy Form Builder on a real WordPress in Chromium (Playwright): real admin pages,
real `admin-ajax.php` and REST requests, the real sanitizer and database. Nothing is stubbed. The
other browser tests in `tests/` load the builder scripts into a blank page with a fake `efb_var`;
this suite is the one that sees what a site owner and a visitor see.

## Scenarios

They run in this order and share the site (one owner's first hour with the plugin). Each scenario
ends with a console check (7).

| # | What |
|---|---|
| 1 | Fresh install (plugin data wiped, plugin re-activated) → plan picker → **Free** → onboarding email step skipped. Database: `emsfb_pro = 2`. |
| 2 | Builder, real clicks: text fields with the mask presets `ir.mobile`, `ir.national_id`, `ir.sheba`, a custom mask (`AA-9999`, upper case), number/amount mode; country → state → city (A1, single-digit country before a double-digit state); the State tile is locked on Free, so **F18** happens here (4a: dialog → Activate Free Plus → field added without reload, gems gone, plan badge, `emsfb_pro = 3`, still Free Plus after a reload); redirect thank-you page (A3); Save; database check; reopen from the panel; second Save stores the same rows. |
| 3 | Shortcode page and block page; logged-out guest: Persian digits typed and pasted, live formatting, caret, checksum errors (national id, Sheba), custom mask, amount grouping; country → state → city; Submit → redirect URL (A3); stored entry is canonical (`+989121234567`, `0012345679`, `IR12…`, `AB1234`, `1234567`); a request tampered after the browser check is refused by the server; response viewer shows the values in `<bdi dir="ltr">`. |
| 4 | F18 after activation: public credit "Powered by Easy Form Builder, the best form plugin for WordPress."; a Pro-only field → S3 (Get Pro, no Activate); an Editor with the plugin's own capabilities (`Emsfb`, `Emsfb_create`, `Emsfb_panel`) may change the plan (owner decision); with the `efb_can_change_plan` filter set to false for non-administrators the Editor sees S7 ("ask an administrator") and a direct AJAX request answers 403. |
| 5 | AutoPilot: `EMSFB_API_VERSION` = `efb_var.api_version` = `efbBuilderApi.version` = 1; `efb_var.capabilities`; `newItems` / `insertItems` / `render()` Promise / `getForm()` deep copy / `setForm` / `refreshSettingsPanel()`; country 9 → state 10 written through the API and still linked on the public page after Save (A1); SMS on without the SMS add-on → Save succeeds with warning `sms_disabled_addon_missing`, stored `smsnoti = 0` (A5); `setFormType('login')` and the Register choice in the form settings add their fields, and the form saves (B4). |
| 6 | F18 dialog S1 in en and fa_IR at 1280 px and 375 px (screenshots, RTL, `<bdi>`, stacked full-width buttons, 44 px targets, no horizontal scroll); on Free: no public credit, the locked field becomes a notice for visitors, the administrator's note links to the dialog (F18 H), the SitePilot link opens and never activates, a scripted click is refused; the public form in fa_IR at 1280 and 375 px (RTL page, LTR masked inputs, Persian error text); the response viewer in fa_IR (RTL panel, values LTR). |
| 8 | F13 privacy: Settings → Privacy (`?tab=privacy`) → "Do not store" → Save; a form with the builder's Privacy section; a guest submits → the answer has no `ip` key, the entry's `ip` is empty (`uid` 0) and the form's visit rows have no IP, OS or browser; back to "Store" → the next entry has the address again. Retention: 90 days typed in Persian digits, the preview ("1 entries will be removed"), Save asks first; `wp cron event run emsfb_daily_maintenance` deletes the entry last active 100 days ago with its reply and its file in `uploads/YYYY/MM/`, the entry with a 10-day-old reply stays; Anonymize + "Run now" → `@prv@` in the database, "Removed for privacy" and the "Anonymized" badge in the response viewer. "Do not store entries": the builder switch shows its warning (en, and fa_IR right to left) and disables "code and link only"; a guest submission gets a confirmation code, no row is stored, the visit counts as sent. WordPress Tools → Export Personal Data (request without the confirmation mail = confirmed at once) → "Download personal data": the plugin's exporter answers the guest's entry (group `efb-entries`) and the downloaded zip holds "Form entries"; Tools → Erase Personal Data erases that entry and keeps the others; Settings → Privacy → Policy Guide shows the suggested text with the current retention. |
| 9 | PAY00 payments with the test gateway `efbmock` (a fake provider on the site, `EFB_PAYMENTS_MOCK` through the mu-plugin; no network): builder tile, settings panel, live canvas, Save of the `pf_*` keys, AutoPilot `newItems` / `formPatch` / `addon_required` / capabilities without keys; a fixed amount (option + fee) charged from the server quote, the entry "pending payment", no e-mail until paid, a bad webhook signature → 401, the signed webhook before the return → paid once, the same webhook again → nothing runs twice, the return → thank-you with the code; return before the webhook; a tampered option price and a tampered browser amount; a computed amount (3 guests × 5 with the Conditional Logic engine) that a lowered request cannot change; donation presets, other amount in Persian digits, limits checked in the page and on the server; a failed payment with "Try again"; the Payments screen (list, details, refund, masked keys, Test connection); fa_IR right to left at 375 px. |
| mollie | PAY01 Mollie add-on (`vendor/mollie`, `AdnMOL`) with a fake `api.mollie.com` (mu-plugin `efb-e2e-mollie.php`, copied into the site by the scenario; answers from `tests/mollie-fake-provider.php` built on the documented responses in `tests/fixtures/payments/mollie`; no network, fake keys): settings card with masked keys and Test connection (`GET /v2/methods`), builder tile + AutoPilot `newItems('mollie')` / `setForm()` of `pf_methods`, a guest pays a fixed amount (method chosen in the field, amount and Idempotency-Key checked at the fake), forged webhook token → 401, Mollie's id-only webhook → refetch → paid once, duplicate → nothing, return → thank-you; return before any webhook; failed → Try again; Payments screen, details, full refund. Helpers: `tests/e2e-wp/ctl-mollie.php` (`fake`, `keys`, `state`, `set`, `token`, `clear-settings`). Leaves `AdnMOL` off. |
| tappay | PAY03 Tap Payments add-on (`vendor/tappay`, `AdnTAP`) with a fake `api.tap.company` (mu-plugin `efb-e2e-tappay.php`, copied into the site by the scenario; answers from `tests/tappay-fake-provider.php` built on Tap's documented responses in `tests/fixtures/payments/tap`; the browser's visit to `checkout.payments.tap.company` is routed to the fake page on the site; no network, fake keys built at run time): settings card with masked keys and Test connection (`POST /v2/charges/list`), builder tile + AutoPilot `newItems('tappay')` (KWD) / `setForm()` of `pf_methods` and grid, a guest pays 12.500 KWD with KNET (mada removed for KWD, KNET note, local marks), `hashstring` webhook wrong / Live key / amount changed → 401, signed CAPTURED before the return → paid once without a GET, duplicate → nothing, return → thank-you; card and return before any webhook (GET); DECLINED → Try again; Payments screen, full refund `12.500` with `reference.idempotent`; fa_IR right to left at 375 px. Helpers: `tests/e2e-wp/ctl-tappay.php` (`fake`, `keys`, `state`, `set`, `webhook`, `clear-settings`). Leaves `AdnTAP` off. |
| payplug | PAY02 Payplug add-on (`vendor/payplug`, `AdnPPG`) with a fake `api.payplug.com` (`mu-plugins/efb-e2e-payplug.php`, copied by `ctl-payplug.php fake 1`; answers from `tests/fixtures/payments/payplug`, a fake hosted page; no network): builder tile, AutoPilot `newItems('payplug')` and capabilities without keys, Save; a guest pays 33.00 EUR (billing from mapped fields), forged notification token → 401, notification before the return → paid once, the same notification again → nothing twice, return → thank-you; return without notification (158.00 EUR) → paid; cancel on Payplug's page → still pending; every request with `PayPlug-Version`; Payments screen refund through Payplug; keys masked; Test connection. |
| omise | PAY04 Omise add-on (`vendor/omise`, `AdnOMS`, PromptPay QR) with a fake `api.omise.co` (`mu-plugins/efb-e2e-omise.php`, copied by `ctl-omise.php install`; answers from `tests/omise-fake-provider.php` built on the documented responses in `tests/fixtures/payments/omise`; no network, fake keys made at run time; the scenario lives in `tests/e2e-wp/scenario-omise.js`): builder tile → THB payment form, fixed 400 baht in the panel, Save, capabilities without keys; a guest presses Pay → the QR in the page as a `data:` URI with countdown (no key or Omise address in the page), payment pending 40000 satang, entry "pending payment"; forged webhook → 401, a signed webhook while Omise says pending changes nothing, Omise successful + signed `charge.complete` → paid once with the e-mails, same webhook again → nothing twice, the page turns to the thank-you; no webhook at all → the page's poll asks Omise → paid; Omise failed → "not completed" + Try again; Payments screen (Omise rows, no Refund button for PromptPay, the dashboard link), gateway settings masked, Test connection. |
| 7 | Console: on every page, any uncaught error and any `console.error` from the plugin's scripts or the page's inline scripts fails the scenario. Allowed (third-party noise only, see `ALLOW` in `lib.js`): a browser "Failed to load resource" for another host that is not reachable from the test machine; WordPress core's jQuery Migrate notices. A failed request to the site itself is never allowed. |

Scenario 6 switches the plan back to Free with WP-CLI for the dialog screenshots and the Free-only
checks, then restores Free Plus. `ctl.php plan` exists only for that and for running one scenario on
its own; the suite otherwise changes plans through the UI.

## Set up the site (once)

PHP 8.x with `pdo_sqlite`, Node 18+, WP-CLI, network access for the WordPress downloads. The site
uses SQLite (no MySQL server needed); MySQL works the same (set `EFB_E2E_SITE_DIR` to that site).

```bash
SITE=/path/to/wpsite/site          # any empty folder
WP="php /path/to/wp-cli.phar"      # or: WP=wp

mkdir -p "$SITE" && cd "$SITE"
$WP core download --version=6.9 --path="$SITE"     # any recent WordPress
# SQLite instead of MySQL:
curl -sSLo /tmp/sqlite.zip https://downloads.wordpress.org/plugin/sqlite-database-integration.zip
unzip -q /tmp/sqlite.zip -d wp-content/plugins/
cp wp-content/plugins/sqlite-database-integration/db.copy wp-content/db.php
sed -i "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SITE/wp-content/plugins/sqlite-database-integration#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" wp-content/db.php
cp wp-config-sample.php wp-config.php
$WP core install --path="$SITE" --url=http://127.0.0.1:8088 --title=EFB --admin_user=admin \
  --admin_password=admin-pass-123 --admin_email=admin@example.test --skip-email

# The plugin under test (this repository) and the suite's mu-plugin:
ln -s /path/to/easy-form-builder wp-content/plugins/easy-form-builder
mkdir -p wp-content/mu-plugins
cp /path/to/easy-form-builder/tests/e2e-wp/mu-plugins/efb-e2e.php wp-content/mu-plugins/
$WP plugin activate easy-form-builder --path="$SITE"
$WP rewrite structure '/%postname%/' --path="$SITE"
$WP language core install fa_IR --path="$SITE"      # is_rtl() needs WordPress's own fa_IR pack

# Serve it:
nohup php -S 127.0.0.1:8088 > ../server.log 2>&1 &
```

The plugin's own Persian files (`languages/easy-form-builder-fa_IR.*`) are used directly from the
repository. Admin user `admin` / `admin-pass-123` are local test defaults only.

## Run

```bash
cd /path/to/easy-form-builder
export EFB_E2E_WPCLI="php /path/to/wp-cli.phar"   # default: wp
export EFB_E2E_SITE_DIR=/path/to/wpsite/site
export EFB_E2E_OUT=/tmp/efb-e2e-out                # screenshots + results.json
node tests/e2e-wp/run.js            # all scenarios (about 2-3 minutes)
node tests/e2e-wp/run.js 1 2 3      # some of them (2 and later need 1 or a site already on Free Plus)
```

| Variable | Default |
|---|---|
| `EFB_E2E_URL` | `http://127.0.0.1:8088` |
| `EFB_E2E_USER` / `EFB_E2E_PASS` | `admin` / `admin-pass-123` |
| `EFB_E2E_WPCLI` | `wp` |
| `EFB_E2E_SITE_DIR` | (WP-CLI's own lookup from the current folder) |
| `EFB_E2E_OUT` | `<system temp>/efb-e2e-out` (screenshots, `results.json`) |
| `EFB_CHROMIUM` | Playwright's Chromium; falls back to an installed headless shell like the other browser tests |

Scenario 1 **wipes the plugin's data** on the site it points at (tables, options, pages and users this
suite created). Point it at a test site only.

`ctl.php` is the suite's window into the database (`wp eval-file tests/e2e-wp/ctl.php <command>`):
`state`, `forms`, `form <id>`, `entries <form_id>`, `page <form_id> [block]`, `user`, `locale`,
`restrict-plan`, `constants`, `plan`, `wipe`, and for F13 `setting <key>`, `sessions <form_id>`, `f13-seed`, `replies <msg_id>`, `file-exists <path>`,
and for PAY00 `pay-mock <0|1>`, `pay-keys <test|live>`, `pay-form <fixed|donation|computed>`, `payments [form_id]`, `pay-events <id>`,
`pay-provider <ref> <status> [amount]`, `pay-webhook-secret`, `mails [clear]`, `reconcile`, `addon-flag <key> <0|1>`; for PAY04 `ctl-omise.php` (`install`, `fake <0|1>`, `keys <test|live> [nosecret]`, `charges`, `set <charge> <status> [amount]`, `event <charge> <key> <id>`).

## SQLite notes

Runs were made on SQLite (sqlite-database-integration 3.1.3). WP-CLI's `wp db query` / `wp db tables`
need the MySQL client and do not see the plugin's tables there; `ctl.php` reads through `$wpdb`
instead, which works on both. No plugin behaviour in these scenarios differed because of SQLite.
