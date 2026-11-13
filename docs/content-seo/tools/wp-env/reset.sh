#!/usr/bin/env bash
# reset.sh -- throw the site away and rebuild it (cached downloads reused). New random admin password.
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
"$HERE/teardown.sh"
exec "$HERE/setup.sh"
