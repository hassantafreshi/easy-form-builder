#!/usr/bin/env bash
# Shared configuration for the EFB tutorial toolchain. `source` this file.
# Everything heavy (WordPress, downloads, videos, secrets) lives under EFB_WORK,
# never inside the repo.
_efb_here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export EFB_TOOLS="$(cd "$_efb_here/.." && pwd)"
export EFB_REPO="${EFB_REPO:-$(cd "$EFB_TOOLS/../../.." && pwd)}"      # the plugin repo
export EFB_WORK="${EFB_WORK:-${TMPDIR:-/tmp}/efb-tutorial}"           # scratch area
export EFB_CACHE="$EFB_WORK/cache"                                    # downloaded archives (reused by reset)
export WP_ROOT="$EFB_WORK/wp"                                         # WordPress docroot
export WP_PORT="${WP_PORT:-8080}"
export WP_HOST="${WP_HOST:-localhost}"                               # NOTE: licence keys are bound to this host name
export WP_URL="http://$WP_HOST:$WP_PORT"
export WP_PHP_WORKERS="${WP_PHP_WORKERS:-4}"                          # php -S worker processes
export EFB_SECRETS_DIR="$EFB_WORK/secrets"                            # outside the repo, mode 700
export EFB_ADMIN_ENV="$EFB_SECRETS_DIR/wp-admin.env"                  # WP_ADMIN_USER / WP_ADMIN_PASS
export EFB_PLAN="${EFB_PLAN:-free_plus}"                              # free | free_plus | none  (pro needs an owner licence key; none = leave plan chooser/onboarding for recording)
export EFB_LOCALES="${EFB_LOCALES:-de_DE ar}"                         # extra WordPress locales to install
# 1 = let the plugin do GET/HEAD to whitestudio.team (add-on archives for Free Plus). POSTs are always blocked.
export EFB_ALLOW_WHITESTUDIO_GET="${EFB_ALLOW_WHITESTUDIO_GET:-1}"
export WP_CLI_PHAR="$EFB_CACHE/wp-cli.phar"

wp() {  # run wp-cli against the local site from a neutral cwd
  (cd / && php -d memory_limit=512M "$WP_CLI_PHAR" --allow-root --path="$WP_ROOT" --url="$WP_URL" "$@")
}
efb_log() { printf '[%s] %s\n' "$(date +%H:%M:%S)" "$*" >&2; }
efb_load_admin() { [ -f "$EFB_ADMIN_ENV" ] && . "$EFB_ADMIN_ENV"; }
