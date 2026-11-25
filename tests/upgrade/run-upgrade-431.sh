#!/usr/bin/env bash
# UPG-01: Easy Form Builder 4.3.1 -> this version on MySQL or MariaDB with large tables, on a throw-away WordPress.
#
#   EFB_UPG_DB_HOST=127.0.0.1:3306 EFB_UPG_DB_USER=… EFB_UPG_DB_PASS=… tests/upgrade/run-upgrade-431.sh
#
# 1. A fresh WordPress (EFB_UPG_WP_VERSION, default latest) on the database EFB_UPG_DB_NAME (default efb_upgrade_test;
#    dropped and created again: give it a server or a user of its own), WP_DEBUG on, WP-Cron off.
# 2. Easy Form Builder 4.3.1 from wordpress.org (EFB_UPG_OLD_ZIP to use a copy; its sha256 is checked against
#    EFB_UPG_OLD_SHA256 when set), activated.
# 3. tests/upgrade/upgrade-lib.php seed: EFB_UPG_ENTRIES entries (default 100000), EFB_UPG_REPLIES replies (20000),
#    sessions, the add-ons' old payments table, legacy Stripe / PayPal / Persia Pay forms, custom Localization texts,
#    keys. A snapshot: counts and a sha256 of every row of each table.
# 4. The plugin folder is replaced by this version (EFB_UPG_NEW: a release zip or folder; default the committed files
#    of HEAD minus .distignore, without the fetched assets), the way an update leaves it, and one request runs the
#    schema update on plugins_loaded. tests/upgrade/efb-upgrade-timer.php (a mu-plugin) times it and keeps the site
#    off the network. PHP errors, warnings and deprecations of that request are collected.
# 5. Snapshots after the update and after the background jobs (wp cron event run --due-now); the upgrade run once more
#    (it must apply nothing); then the comparison: the same rows and content in forms, entries, replies, sessions,
#    payments and settings; the new tables and columns; the time against EFB_UPG_MAX_SECONDS (default 30, the release
#    exit criterion).
#
# Writes $EFB_UPG_DIR/result.json (default a new temporary folder) and prints [PASS] / [FAIL] lines and RESULTS.
# Needs php (mysqli), unzip, curl (for a download) and WP-CLI (EFB_WPCLI, default wp). No mysql client is needed.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
: "${EFB_UPG_DB_HOST:?set EFB_UPG_DB_HOST (host:port)}" "${EFB_UPG_DB_USER:?set EFB_UPG_DB_USER}"
DB_PASS="${EFB_UPG_DB_PASS:-}"
DB_NAME="${EFB_UPG_DB_NAME:-efb_upgrade_test}"
WPCLI="${EFB_WPCLI:-wp}"
DIR="${EFB_UPG_DIR:-$(mktemp -d)}"
ENTRIES="${EFB_UPG_ENTRIES:-100000}"
REPLIES="${EFB_UPG_REPLIES:-20000}"
MAX="${EFB_UPG_MAX_SECONDS:-30}"
WPV="${EFB_UPG_WP_VERSION:-latest}"
OLD_ZIP="${EFB_UPG_OLD_ZIP:-}"
NEW="${EFB_UPG_NEW:-}"
WP="$DIR/wp"
PLUG="$WP/wp-content/plugins"
LIB="$ROOT/tests/upgrade/upgrade-lib.php"
mkdir -p "$DIR"
# WP-CLI runs from the test folder: emsfb.php requires 'includes/class-Emsfb.php' by a relative path, which PHP resolves
# against the current folder first (from this repository it would load this version's class into the 4.3.1 plugin).
cd "$DIR"
# shellcheck disable=SC2086 # EFB_WPCLI may be "php /path/wp-cli.phar"
wp_() { $WPCLI --path="$WP" --allow-root "$@"; }
say() { printf '%s\n' "$*"; }

say "== 4.3.1 -> $(sed -n 's/^[ *]*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$ROOT/emsfb.php" | head -1) on $EFB_UPG_DB_HOST/$DB_NAME, $ENTRIES entries ($DIR)"

# 1. Database and WordPress.
php -r '
  [$_, $hp, $u, $pw, $db] = $argv;
  [$h, $port] = array_pad(explode(":", $hp, 2), 2, 3306);
  $m = new mysqli($h, $u, $pw, "", (int) $port);
  if ($m->connect_errno) { fwrite(STDERR, "database: " . $m->connect_error . "\n"); exit(1); }
  $m->query("DROP DATABASE IF EXISTS `$db`");
  if (!$m->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) { fwrite(STDERR, $m->error . "\n"); exit(1); }
  echo "database: " . $m->server_info . "\n";
' "$EFB_UPG_DB_HOST" "$EFB_UPG_DB_USER" "$DB_PASS" "$DB_NAME"
if [ ! -f "$WP/wp-load.php" ]; then
  mkdir -p "$WP"
  wp_ core download --version="$WPV" --quiet
fi
rm -f "$WP/wp-config.php"
wp_ config create --dbname="$DB_NAME" --dbuser="$EFB_UPG_DB_USER" --dbpass="$DB_PASS" --dbhost="$EFB_UPG_DB_HOST" --skip-check --quiet \
  --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'WP_DEBUG_LOG', false );
define( 'DISABLE_WP_CRON', true );
PHP
wp_ core install --url=http://127.0.0.1:8097 --title="EFB upgrade test" --admin_user=admin --admin_password=admin-pass-123 \
  --admin_email=admin@example.test --skip-email --quiet
say "wordpress: $(wp_ core version)"

# 2. 4.3.1.
if [ -z "$OLD_ZIP" ]; then
  OLD_ZIP="$DIR/easy-form-builder.4.3.1.zip"
  [ -f "$OLD_ZIP" ] || curl -fsSL -o "$OLD_ZIP" https://downloads.wordpress.org/plugin/easy-form-builder.4.3.1.zip
fi
if [ -n "${EFB_UPG_OLD_SHA256:-}" ]; then
  [ "$(sha256sum "$OLD_ZIP" | cut -d' ' -f1)" = "$EFB_UPG_OLD_SHA256" ] || { say "the 4.3.1 zip's sha256 differs"; exit 1; }
fi
rm -rf "$PLUG/easy-form-builder" "$WP/wp-content/mu-plugins"
unzip -q "$OLD_ZIP" -d "$PLUG/"
grep -q "Version:[[:space:]]*4.3.1" "$PLUG/easy-form-builder/emsfb.php" || { say "the old zip is not 4.3.1"; exit 1; }
wp_ plugin activate easy-form-builder --quiet
say "4.3.1 active, database version $(wp_ option get Emsfb_db_version)"

# 3. Data.
say "seed: $(wp_ eval-file "$LIB" seed "$ENTRIES" "$REPLIES")"
say "before: $(wp_ eval-file "$LIB" snapshot "$DIR/before.json")"

# 4. This version, as an update leaves it.
rm -rf "$PLUG/easy-form-builder"
if [ -z "$NEW" ]; then
  mkdir -p "$DIR/new-archive" "$PLUG/easy-form-builder"
  git -C "$ROOT" archive --format=tar HEAD | tar -x -C "$DIR/new-archive"
  rsync -a --exclude-from="$DIR/new-archive/.distignore" "$DIR/new-archive/" "$PLUG/easy-form-builder/"
  rm -rf "$DIR/new-archive"
elif [ -d "$NEW" ]; then
  cp -R "$NEW" "$PLUG/easy-form-builder"
else
  unzip -q "$NEW" -d "$PLUG/"
fi
mkdir -p "$WP/wp-content/mu-plugins"
cp "$ROOT/tests/upgrade/efb-upgrade-timer.php" "$WP/wp-content/mu-plugins/"
start=$(date +%s.%N)
wp_ eval 'echo "database version " . get_option("Emsfb_db_version") . "\n";' > "$DIR/upgrade-request.out" 2> "$DIR/upgrade-request.err" || true
end=$(date +%s.%N)
cat "$DIR/upgrade-request.out"
say "the first request after the update took $(php -r "printf('%.1f', $end - $start);") s (WordPress start included)"
grep -E 'PHP (Fatal|Parse|Warning|Notice|Deprecated)|^(Fatal error|Warning|Notice|Deprecated):' "$DIR/upgrade-request.err" "$DIR/upgrade-request.out" > "$DIR/php-errors.txt" || true

# 5. After the update, after its background jobs, and a second run.
say "after: $(wp_ eval-file "$LIB" snapshot "$DIR/after.json" "$DIR/before.json")"
wp_ cron event run --due-now --quiet > "$DIR/cron.out" 2>&1 || true
grep -E 'PHP (Fatal|Parse|Warning|Notice|Deprecated)|^(Fatal error|Warning|Notice|Deprecated):' "$DIR/cron.out" >> "$DIR/php-errors.txt" || true
say "after the background jobs: $(wp_ eval-file "$LIB" snapshot "$DIR/after-cron.json" "$DIR/before.json")"
say "second run: $(wp_ eval-file "$LIB" rerun "$DIR/rerun.json")"
wp_ eval-file "$LIB" compare "$DIR/before.json" "$DIR/after.json" "$DIR/after-cron.json" "$DIR/rerun.json" timing "$MAX" "$DIR/php-errors.txt" "$DIR/result.json" | tee "$DIR/compare.out"
say "result: $DIR/result.json"
grep -q '^RESULTS: [0-9]* passed, 0 failed' "$DIR/compare.out"
