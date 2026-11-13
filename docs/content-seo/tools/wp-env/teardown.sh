#!/usr/bin/env bash
# teardown.sh -- stop the server and delete the site. Keeps the download cache; add --all to delete everything
# (cache + secrets + captures) under $EFB_WORK.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"
"$(dirname "$0")/server.sh" stop || true
rm -rf "$WP_ROOT" "$EFB_WORK/router.php" "$EFB_WORK/php-server.log"
if [ "${1:-}" = "--all" ]; then rm -rf "$EFB_WORK"; fi
efb_log "torn down ($([ "${1:-}" = "--all" ] && echo everything || echo "site only; cache kept"))"
