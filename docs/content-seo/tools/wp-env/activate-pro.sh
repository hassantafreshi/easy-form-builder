#!/usr/bin/env bash
# activate-pro.sh -- OPTIONAL, OWNER-DRIVEN: activate Pro on the local recording site with a licence key the owner supplies.
#
#   1. owner puts the key (one line) in  $EFB_SECRETS_DIR/pro-key.txt   (outside the repo, chmod 600)
#      The key must have been issued for the host this site runs under ($WP_HOST, default "localhost") - the plugin
#      embeds md5(host) in every activation code and refuses codes that do not match this domain.
#   2. run this script. It calls the plugin's OWN activation routine (Easy Form Builder -> Settings -> "Activation code"
#      does the same): get_efbFunction()->is_efb_pro($key). That makes ONE POST to https://whitestudio.team/wp-json/wl/v1/pro/key
#      carrying domain, admin e-mail, WP/PHP/plugin versions and the active-plugin list. The mu-plugin blocks that POST at
#      all other times.
#
# Nothing here bypasses or edits any licence check. STATUS: untested in the spike (no key was available).
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"
KEYFILE="${EFB_PRO_KEY_FILE:-$EFB_SECRETS_DIR/pro-key.txt}"
# The pipeline gets the key from the environment variable EFB_LICENSE_KEY (project environment settings); a file is the fallback.
if [ -n "${EFB_LICENSE_KEY:-}" ] && [ ! -s "$KEYFILE" ]; then
  umask 077; mkdir -p "$(dirname "$KEYFILE")"; printf '%s\n' "$EFB_LICENSE_KEY" > "$KEYFILE"
fi
[ -s "$KEYFILE" ] || { efb_log "no licence key (EFB_LICENSE_KEY or $KEYFILE) - the key must be issued for host '$WP_HOST'"; exit 2; }
wp config set EFB_ENV_ALLOW_LICENSE_POST true --raw >/dev/null
trap 'wp config set EFB_ENV_ALLOW_LICENSE_POST false --raw >/dev/null' EXIT
KEY="$(tr -d '[:space:]' < "$KEYFILE")" wp eval '
  $ok = get_efbFunction()->is_efb_pro( getenv("KEY") );
  echo $ok ? "PRO ACTIVE (emsfb_pro=" . get_option("emsfb_pro") . ")\n" : "activation refused - plan stays emsfb_pro=" . get_option("emsfb_pro") . "\n";
'
