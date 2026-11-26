#!/usr/bin/env bash
# SEC-02 (owner decision 9): the fonts of the response box, the country / province / city lists and Chart.js are
# served from the plugin, never from a CDN on the visitor's page. They are third-party files (OFL fonts, the MIT
# country lists, MIT Chart.js) at pinned versions, fetched once when the release is built and shipped in the zip
# (they are not kept in git: tests/test-split-core-size.php guards the size of the code in the repository).
#
#   bin/fetch-public-assets.sh [plugin folder]     (default: the folder above bin/)
#
# Writes (and replaces):
#   public/assets/fonts/<slug>/font.css + *.woff2   one folder per font of Settings › Response box (local @font-face)
#   public/assets/fonts/README.txt                  each font's source, version and licence
#   public/assets/json/geo/                         json/countries.json, json/states/<cc>.json, json/cites/<cc>/<st>.json,
#                                                   js/wp/countries.js (the builder's country list)
#   public/assets/js/vendor/chart.umd.min.js        Chart.js for the poll results
#   public/assets/fetched-assets.sha256             what was fetched, for the release notes and a later audit
# The plugin uses each of them only when it is there (_Public::local_font_css_efb(), ::geo_base_url_efb(),
# poll-chart-efb.js); without them the fonts fall back to the system's and the lists to the CDN.
# Needs curl, python3 and git. B1's bin/build-zip.sh runs it before it packs the zip.
set -euo pipefail
ROOT="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
FONTS="$ROOT/public/assets/fonts"
GEO="$ROOT/public/assets/json/geo"
VENDOR_JS="$ROOT/public/assets/js/vendor"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
UA='Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36' # Google answers woff2 to a current browser

# The country lists (the plugin author's dataset, MIT), pinned to a commit.
GEO_REPO='https://github.com/hassantafreshi/Json-List-of-countries-states-and-cities-in-the-world.git'
GEO_COMMIT='713bb95f004355619c952225d8a1c30534c38579'
CHART_URL='https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'

# slug | family as Settings stores it | source
GOOGLE_FONTS=(
  "vazirmatn|Vazirmatn|Vazirmatn:wght@400;700"
  "lalezar|Lalezar|Lalezar"
  "cairo|Cairo|Cairo:wght@400;700"
  "tajawal|Tajawal|Tajawal:wght@400;700"
  "noto-sans-arabic|Noto Sans Arabic|Noto+Sans+Arabic:wght@400;700"
  "ibm-plex-sans-arabic|IBM Plex Sans Arabic|IBM+Plex+Sans+Arabic:wght@400;700"
  "amiri|Amiri|Amiri:ital,wght@0,400;0,700"
  "noto-kufi-arabic|Noto Kufi Arabic|Noto+Kufi+Arabic:wght@400;700"
  "inter|Inter|Inter:wght@400;700"
  "roboto|Roboto|Roboto:wght@400;700"
  "open-sans|Open Sans|Open+Sans:wght@400;700"
)
# slug | package@version (github.com/rastikerdar, OFL 1.1)
PERSIAN_FONTS=(
  "vazir|vazir-font@30.1.0"
  "sahel|sahel-font@3.4.0"
  "samim|samim-font@4.0.5"
  "shabnam|shabnam-font@5.0.1"
  "parastoo|parastoo-font@2.0.1"
  "gandom|gandom-font@0.8"
)

rm -rf "$FONTS" "$GEO" "$VENDOR_JS/chart.umd.min.js"
mkdir -p "$FONTS" "$GEO" "$VENDOR_JS"
{
  echo "Fonts of Easy Form Builder's response box, served from the plugin (no request to a font CDN)."
  # No date here: this file is pinned in bin/public-assets.lock.sha256, and a date made every build on another day
  # stop at the lock check (and two builds on different days differ).
  echo "Fetched by bin/fetch-public-assets.sh at the versions below. Each font keeps its own licence."
  echo
} > "$FONTS/README.txt"

# Google Fonts: the arabic, latin and latin-ext subsets, woff2, the CSS rewritten to the local files.
for row in "${GOOGLE_FONTS[@]}"; do
  IFS='|' read -r slug family query <<< "$row"
  mkdir -p "$FONTS/$slug"
  curl -fsS -A "$UA" "https://fonts.googleapis.com/css2?family=${query}&display=swap" -o "$TMP/$slug.css"
  python3 - "$TMP/$slug.css" "$FONTS/$slug" <<'PY'
import re, sys, urllib.request, hashlib, os
css, out = sys.argv[1], sys.argv[2]
blocks = re.findall(r'/\* ([a-z-]+) \*/\s*(@font-face \{.*?\})', open(css, encoding='utf-8').read(), re.S)
keep, n = [], 0
for subset, block in blocks:
    if subset not in ('arabic', 'latin', 'latin-ext'):
        continue
    url = re.search(r'url\((https://fonts\.gstatic\.com/[^)]+\.woff2)\)', block).group(1)
    name = subset + '-' + hashlib.sha1(url.encode()).hexdigest()[:10] + '.woff2'
    if not os.path.exists(os.path.join(out, name)):
        urllib.request.urlretrieve(url, os.path.join(out, name)); n += 1
    keep.append('/* ' + subset + ' */\n' + block.replace(url, name))
if not keep:
    sys.exit('no arabic / latin face in ' + css)
open(os.path.join(out, 'font.css'), 'w', encoding='utf-8').write('\n'.join(keep) + '\n')
PY
  printf '%s (%s): Google Fonts, css2?family=%s, subsets arabic latin latin-ext. SIL Open Font License 1.1 (Roboto: Apache License 2.0 / OFL).\n' "$family" "$slug" "$query" >> "$FONTS/README.txt"
done

# rastikerdar's Persian fonts: their font-face.css at a pinned version, woff2 only.
for row in "${PERSIAN_FONTS[@]}"; do
  IFS='|' read -r slug pkg <<< "$row"
  base="https://cdn.jsdelivr.net/gh/rastikerdar/${pkg}/dist"
  mkdir -p "$FONTS/$slug"
  curl -fsS "$base/font-face.css" -o "$TMP/$slug.css"
  python3 - "$TMP/$slug.css" "$FONTS/$slug" "$base" <<'PY'
import re, sys, urllib.request, os
css, out, base = sys.argv[1], sys.argv[2], sys.argv[3]
faces = re.findall(r'@font-face\s*\{.*?\}', open(css, encoding='utf-8').read(), re.S)
keep = []
for f in faces:
    m = re.search(r"url\(['\"]?([^'\")?#]+\.woff2)['\"]?\)", f)
    if not m:
        continue
    rel = m.group(1)
    name = os.path.basename(rel)
    urllib.request.urlretrieve(base + '/' + rel, os.path.join(out, name))
    body = re.sub(r'\s*src:[^;]*;', '', f[f.index('{') + 1:f.rindex('}')], flags=re.S)
    keep.append("@font-face {\n  src: url('" + name + "') format('woff2');" + body.rstrip() + ("" if "font-display" in body else "\n  font-display: swap;") + "\n}")
if not keep:
    sys.exit('no woff2 face in ' + css)
open(os.path.join(out, 'font.css'), 'w', encoding='utf-8').write('\n'.join(keep) + '\n')
PY
  printf '%s: github.com/rastikerdar/%s (dist/font-face.css, woff2). SIL Open Font License 1.1.\n' "$slug" "$pkg" >> "$FONTS/README.txt"
done

# The country, province and city lists.
git init -q "$TMP/geo"
git -C "$TMP/geo" fetch -q --depth 1 "$GEO_REPO" "$GEO_COMMIT"
git -C "$TMP/geo" checkout -q FETCH_HEAD -- json js/wp/countries.js LICENSE
mkdir -p "$GEO/json" "$GEO/js/wp"
cp -R "$TMP/geo/json/." "$GEO/json/"
cp "$TMP/geo/js/wp/countries.js" "$GEO/js/wp/countries.js"
cp "$TMP/geo/LICENSE" "$GEO/LICENSE"
# A few files start with a byte-order mark; JSON has none.
find "$GEO/json" -name '*.json' -print0 | xargs -0 python3 -c "
import sys
for p in sys.argv[1:]:
    b = open(p, 'rb').read()
    if b.startswith(b'\xef\xbb\xbf'): open(p, 'wb').write(b[3:])"
printf 'Country, province and city lists: %s at %s (MIT).\n' "$GEO_REPO" "$GEO_COMMIT" > "$GEO/README.txt"

# Chart.js (MIT) for the poll results.
curl -fsS "$CHART_URL" -o "$VENDOR_JS/chart.umd.min.js"

# (a few list files have spaces in their names)
( cd "$ROOT" && find public/assets/fonts public/assets/json/geo public/assets/js/vendor/chart.umd.min.js -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum ) > "$ROOT/public/assets/fetched-assets.sha256"
echo "fonts: $(du -sh "$FONTS" | cut -f1), lists: $(find "$GEO/json" -name '*.json' | wc -l) files $(du -sh "$GEO" | cut -f1), chart.js: $(wc -c < "$VENDOR_JS/chart.umd.min.js") bytes"
