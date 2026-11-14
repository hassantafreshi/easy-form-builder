#!/usr/bin/env bash
# set-locale.sh <locale>  -- switch the local recording site to one language (en_US | de_DE | ar | ...).
#
# Sets BOTH the site language (WPLANG, read by get_locale(): Easy Form Builder takes its RTL flag, the front end and
# parts of its admin strings from it) and the admin user's locale (empty = follow the site), so the WordPress chrome
# and the plugin never end up in two languages. Also applies the IMAGE-STYLE-GUIDE identity (Acme Demo /
# Demo Admin / demo@example.com). The language pack must already be installed (install-languages.sh).
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"
LOC="${1:?usage: set-locale.sh <locale>}"
[[ "$LOC" =~ ^[a-z]{2,3}(_[A-Z]{2})?$ ]] || { efb_log "bad locale: $LOC"; exit 1; }
efb_load_admin
if [ "$LOC" = en_US ]; then wp option update WPLANG '' >/dev/null
else
  [ -f "$WP_ROOT/wp-content/languages/$LOC.mo" ] || "$(dirname "$0")/install-languages.sh" "$LOC"
  wp option update WPLANG "$LOC" >/dev/null
fi
wp user meta update "$WP_ADMIN_USER" locale '' >/dev/null
wp option update blogname "Acme Demo" >/dev/null
wp option update admin_email "demo@example.com" >/dev/null
wp user update "$WP_ADMIN_USER" --display_name="Demo Admin" --first_name=Demo --last_name=Admin --user_email=demo@example.com --skip-email >/dev/null
efb_log "site + admin locale = $LOC"
