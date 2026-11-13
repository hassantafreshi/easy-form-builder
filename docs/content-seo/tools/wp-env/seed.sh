#!/usr/bin/env bash
# seed.sh -- (re)create the demo forms + front-end pages. Safe to run repeatedly (upserts by form name).
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"
wp eval-file "$EFB_TOOLS/wp-env/seed-forms.php"
