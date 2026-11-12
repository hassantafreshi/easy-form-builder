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
export EFB_E2E_OUT=/tmp/efb-e2e-out                # screenshots + results.json (default tests/e2e-wp/out)
node tests/e2e-wp/run.js            # all scenarios (about 2-3 minutes)
node tests/e2e-wp/run.js 1 2 3      # some of them (2 and later need 1 or a site already on Free Plus)
```

| Variable | Default |
|---|---|
| `EFB_E2E_URL` | `http://127.0.0.1:8088` |
| `EFB_E2E_USER` / `EFB_E2E_PASS` | `admin` / `admin-pass-123` |
| `EFB_E2E_WPCLI` | `wp` |
| `EFB_E2E_SITE_DIR` | (WP-CLI's own lookup from the current folder) |
| `EFB_E2E_OUT` | `tests/e2e-wp/out` (git-ignored) |
| `EFB_CHROMIUM` | Playwright's Chromium; falls back to an installed headless shell like the other browser tests |

Scenario 1 **wipes the plugin's data** on the site it points at (tables, options, pages and users this
suite created). Point it at a test site only.

`ctl.php` is the suite's window into the database (`wp eval-file tests/e2e-wp/ctl.php <command>`):
`state`, `forms`, `form <id>`, `entries <form_id>`, `page <form_id> [block]`, `user`, `locale`,
`restrict-plan`, `constants`, `plan`, `wipe`.

## SQLite notes

Runs were made on SQLite (sqlite-database-integration 3.1.3). WP-CLI's `wp db query` / `wp db tables`
need the MySQL client and do not see the plugin's tables there; `ctl.php` reads through `$wpdb`
instead, which works on both. No plugin behaviour in these scenarios differed because of SQLite.
