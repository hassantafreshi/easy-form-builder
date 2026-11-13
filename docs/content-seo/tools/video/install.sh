#!/usr/bin/env bash
# install.sh -- install the Remotion project's npm dependencies OUTSIDE the repo ($EFB_WORK/video-deps)
# and symlink node_modules into this folder (the symlink is git-ignored). Idempotent.
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$HERE/../wp-env/env.sh"
DEPS="$EFB_WORK/video-deps"
mkdir -p "$DEPS"
cp "$HERE/package.json" "$DEPS/package.json"
[ -f "$HERE/package-lock.json" ] && cp "$HERE/package-lock.json" "$DEPS/package-lock.json"
if [ ! -d "$DEPS/node_modules/remotion" ] || ! cmp -s "$HERE/package.json" "$DEPS/.installed-package.json" 2>/dev/null; then
  efb_log "npm install (remotion project) into $DEPS"
  (cd "$DEPS" && npm install --no-audit --no-fund --loglevel=error)
  cp "$HERE/package.json" "$DEPS/.installed-package.json"
  cp "$DEPS/package-lock.json" "$HERE/package-lock.json"
fi
ln -sfn "$DEPS/node_modules" "$HERE/node_modules"
efb_log "remotion deps ready: $(node -e "console.log(require('$DEPS/node_modules/remotion/package.json').version)")"
