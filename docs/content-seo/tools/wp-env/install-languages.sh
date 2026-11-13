#!/usr/bin/env bash
# install-languages.sh [locale ...]  -- install WordPress core + Easy Form Builder translation packs for locales.
#
# Why not `wp language ...`? In the cloud container WordPress's own HTTP client was answered with
# "HTTP 434" by api.wordpress.org (observed on every call), so core/plugin language installs through
# WordPress itself failed. This script asks the same public translations API with curl and unpacks the
# official packages (downloads.wordpress.org) into wp-content/languages - exactly what WordPress does.
# Packs are cached in $EFB_CACHE/lang.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"
LOCALES=("$@"); [ ${#LOCALES[@]} -gt 0 ] || read -r -a LOCALES <<<"$EFB_LOCALES"
WPV="$(wp core version)"; EFBV="$(wp plugin get easy-form-builder --field=version)"
LANG_DIR="$WP_ROOT/wp-content/languages"; mkdir -p "$LANG_DIR/plugins" "$EFB_CACHE/lang"
core_json="$EFB_CACHE/lang/core-$WPV.json"; plug_json="$EFB_CACHE/lang/plugin-efb-$EFBV.json"
[ -s "$core_json" ] || curl -fsSL --retry 3 "https://api.wordpress.org/translations/core/1.0/?version=$WPV" -o "$core_json"
[ -s "$plug_json" ] || curl -fsSL --retry 3 "https://api.wordpress.org/translations/plugins/1.0/?slug=easy-form-builder&version=$EFBV" -o "$plug_json"

fetch_unzip() { # <package-url> <dest-dir> <label>
  local url="$1" dest="$2" label="$3" zip
  zip="$EFB_CACHE/lang/$(echo "$url" | sed 's#[^A-Za-z0-9._-]#_#g')"
  if [ ! -s "$zip" ]; then
    curl -fsSL --retry 3 --max-time 120 -o "$zip.part" "$url" || { rm -f "$zip.part"; efb_log "  $label: download failed ($url)"; return 1; }
    mv "$zip.part" "$zip"
  fi
  unzip -q -o "$zip" -d "$dest"
}
for loc in "${LOCALES[@]}"; do
  [ "$loc" = en_US ] && continue
  core_pkg="$(jq -r --arg l "$loc" '.translations[]|select(.language==$l)|.package' "$core_json" | head -1)"
  plug_pkg="$(jq -r --arg l "$loc" '.translations[]|select(.language==$l)|.package' "$plug_json" | head -1)"
  if [ -n "$core_pkg" ]; then fetch_unzip "$core_pkg" "$LANG_DIR" "core $loc" && efb_log "  core $loc ok"; else efb_log "  core $loc: no pack"; fi
  if [ -n "$plug_pkg" ]; then fetch_unzip "$plug_pkg" "$LANG_DIR/plugins" "efb $loc" && efb_log "  easy-form-builder $loc ok (wordpress.org pack)"
  else efb_log "  easy-form-builder $loc: no wordpress.org pack (bundled file in plugin languages/ is used if any)"; fi
done
