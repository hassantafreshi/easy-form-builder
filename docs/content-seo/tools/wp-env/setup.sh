#!/usr/bin/env bash
# setup.sh -- build the local recording site from scratch (WordPress + SQLite + Easy Form Builder).
#
#   EFB_WORK=/some/dir WP_PORT=8080 ./setup.sh
#
# Idempotent from the outside: it always deletes $WP_ROOT and rebuilds it. Downloaded archives are cached in
# $EFB_CACHE (delete that folder to force fresh downloads). Admin password is random, written ONLY to
# $EFB_ADMIN_ENV (mode 600, outside the repo). Takes ~1-2 min with a warm cache.
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$HERE/env.sh"
T0=$(date +%s)

for bin in php curl unzip tar; do command -v "$bin" >/dev/null || { efb_log "missing prerequisite: $bin"; exit 1; }; done
php -r 'exit(extension_loaded("pdo_sqlite") && extension_loaded("zip") ? 0 : 1);' || { efb_log "php needs pdo_sqlite + zip"; exit 1; }

mkdir -p "$EFB_CACHE" "$EFB_SECRETS_DIR"; chmod 700 "$EFB_SECRETS_DIR"

# ---- 1. downloads (cached, checksum-verified) ---------------------------------------------------------------
dl() { # dl <url> <dest>
  [ -s "$2" ] && return 0
  curl -fsSL --retry 3 --retry-delay 2 --max-time 300 -o "$2.part" "$1" && mv "$2.part" "$2"
}
efb_log "1/9 downloading (cache: $EFB_CACHE)"
dl https://wordpress.org/latest.zip "$EFB_CACHE/wordpress-latest.zip"
dl https://wordpress.org/latest.zip.sha1 "$EFB_CACHE/wordpress-latest.zip.sha1"
echo "$(cat "$EFB_CACHE/wordpress-latest.zip.sha1")  $EFB_CACHE/wordpress-latest.zip" | sha1sum -c - >/dev/null \
  || { rm -f "$EFB_CACHE"/wordpress-latest.zip*; efb_log "WordPress checksum mismatch (cache cleared, re-run)"; exit 1; }
dl https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip "$EFB_CACHE/sqlite-database-integration.zip"
dl https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar "$WP_CLI_PHAR"
dl https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.sha512 "$WP_CLI_PHAR.sha512"
echo "$(cat "$WP_CLI_PHAR.sha512")  $WP_CLI_PHAR" | sha512sum -c - >/dev/null \
  || { rm -f "$WP_CLI_PHAR"*; efb_log "wp-cli checksum mismatch (cache cleared, re-run)"; exit 1; }

# ---- 2. fresh extraction (archives are untrusted: new empty dir, scripts run from elsewhere) -----------------
efb_log "2/9 extracting WordPress"
"$HERE/server.sh" stop >/dev/null 2>&1 || true
rm -rf "$WP_ROOT"; EXTRACT="$(mktemp -d "$EFB_WORK/extract.XXXXXX")"
unzip -q "$EFB_CACHE/wordpress-latest.zip" -d "$EXTRACT"
mv "$EXTRACT/wordpress" "$WP_ROOT"; rm -rf "$EXTRACT"
rm -rf "$WP_ROOT/wp-content/plugins/hello.php" "$WP_ROOT/wp-content/plugins/akismet"

# ---- 3. SQLite Database Integration (no MySQL) --------------------------------------------------------------
efb_log "3/9 installing SQLite Database Integration drop-in"
unzip -q "$EFB_CACHE/sqlite-database-integration.zip" -d "$WP_ROOT/wp-content/plugins/"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WP_ROOT/wp-content/plugins/sqlite-database-integration#g" \
    -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" \
    "$WP_ROOT/wp-content/plugins/sqlite-database-integration/db.copy" > "$WP_ROOT/wp-content/db.php"
mkdir -p "$WP_ROOT/wp-content/database" "$WP_ROOT/wp-content/mu-plugins"
cp "$HERE/mu-plugins/efb-tutorial-env.php" "$WP_ROOT/wp-content/mu-plugins/"

# ---- 4. wp-config + install ---------------------------------------------------------------------------------
efb_log "4/9 wp-config + core install"
wp config create --dbname=wordpress --dbuser=unused --dbpass=unused --dbhost=localhost --skip-check --force \
   --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
PHP
wp config set EFB_ENV_ALLOW_WHITESTUDIO_GET "$([ "$EFB_ALLOW_WHITESTUDIO_GET" = 1 ] && echo true || echo false)" --raw >/dev/null

umask 077
WP_ADMIN_USER="${WP_ADMIN_USER:-efbadmin}"
WP_ADMIN_PASS="$(php -r 'echo bin2hex(random_bytes(14));')"
printf 'WP_ADMIN_USER=%q\nWP_ADMIN_PASS=%q\nWP_URL=%q\n' "$WP_ADMIN_USER" "$WP_ADMIN_PASS" "$WP_URL" > "$EFB_ADMIN_ENV"
chmod 600 "$EFB_ADMIN_ENV"; umask 022

wp core install --url="$WP_URL" --title="Easy Form Builder Demo" --admin_user="$WP_ADMIN_USER" \
   --admin_password="$WP_ADMIN_PASS" --admin_email="admin@example.test" --skip-email >/dev/null
wp option update timezone_string "UTC" >/dev/null
wp option update blogdescription "Tutorial recording site" >/dev/null
wp rewrite structure '/%postname%/' >/dev/null
wp user meta update "$WP_ADMIN_USER" show_welcome_panel 0 >/dev/null
wp user meta update "$WP_ADMIN_USER" locale en_US >/dev/null
wp post delete 1 2 --force >/dev/null 2>&1 || true   # default hello-world / sample page

# ---- 5. Easy Form Builder plugin (copied, not symlinked: seed scripts + __FILE__ need the real path) ---------
efb_log "5/9 installing Easy Form Builder from $EFB_REPO"
PLUGIN_DIR="$WP_ROOT/wp-content/plugins/easy-form-builder"
rm -rf "$PLUGIN_DIR"; mkdir -p "$PLUGIN_DIR"
tar -C "$EFB_REPO" --exclude='./.git' --exclude='./node_modules' --exclude='./docs' --exclude='./tests' \
    --exclude='*.zip' --exclude='./debug-helper.js' --exclude='./package*.json' --exclude='./composer.json' -cf - . \
  | tar -C "$PLUGIN_DIR" -xf -
wp plugin activate easy-form-builder >/dev/null
EFB_VER="$(wp plugin get easy-form-builder --field=version)"

# ---- 6. choose the plan (the same option the in-plugin plan chooser writes) ---------------------------------
efb_log "6/9 plan = $EFB_PLAN  (free=2, free_plus=3; Pro=1 requires a licence activated by the owner)"
case "$EFB_PLAN" in
  free) wp option update emsfb_pro 2 >/dev/null ;;
  free_plus) wp option update emsfb_pro 3 >/dev/null ;;
  *) efb_log "unknown EFB_PLAN=$EFB_PLAN"; exit 1 ;;
esac

# ---- 7. languages ---------------------------------------------------------------------------------------------
efb_log "7/9 languages: en_US $EFB_LOCALES"
for loc in $EFB_LOCALES; do
  wp language core install "$loc" >/dev/null 2>&1 || efb_log "  core language $loc failed"
  # de_DE is bundled in the plugin; others come from translate.wordpress.org plugin packs
  wp language plugin install easy-form-builder "$loc" >/dev/null 2>&1 || efb_log "  plugin language pack $loc unavailable (falls back to bundled/English)"
done

# ---- 8. server + seed -----------------------------------------------------------------------------------------
efb_log "8/9 starting PHP server on $WP_URL"
"$HERE/server.sh" start
efb_log "8/9 seeding forms"
"$HERE/seed.sh"

# ---- 9. summary -----------------------------------------------------------------------------------------------
efb_log "9/9 done in $(( $(date +%s) - T0 ))s  | WordPress $(wp core version) | EFB $EFB_VER | $WP_URL/wp-admin | user $WP_ADMIN_USER | password in $EFB_ADMIN_ENV"
