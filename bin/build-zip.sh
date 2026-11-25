#!/usr/bin/env bash
# REL-04: the reproducible release zip of Easy Form Builder.
#
#   bin/build-zip.sh [--ref <commit>] [--skip-fetch] [--update-asset-lock] [--no-plugin-check]
#
# 1. Packs the files committed at <commit> (default HEAD) with `git archive`, minus .distignore. Uncommitted changes
#    are never packed (the script names them so nobody thinks they are in the zip).
# 2. Runs bin/fetch-public-assets.sh in the release folder (fonts, the country / province / city lists, Chart.js at
#    pinned versions; SEC-02) and checks every fetched file against bin/public-assets.lock.sha256. A difference stops
#    the build; after reviewing it, --update-asset-lock writes the new lock (commit it). --skip-fetch builds without
#    them (a development zip: the plugin then falls back to the CDNs; not for release).
# 3. Writes the minified twin (x.min.js / x.min.css; terser and csso at pinned versions) of every asset
#    _Public::asset_efb() can pick: each asset_efb('…') path in includes/, and each bundled font.css.
# 4. Checks the folder (top-level entries, no excluded or development file, no "@" in a file name, PHP syntax, the
#    twins), sets every time to the commit's time, and zips it in a fixed order (twins last, so an unzip writes each
#    twin after its source) with fixed permissions: the same commit and the same assets give the same zip, byte
#    for byte.
# 5. Writes dist/easy-form-builder-<version>.zip, .zip.sha256, .sha256 (sha256sum -c list of every file) and
#    .manifest.json (commit, versions, the zip's sha256, each file's size and sha256).
# 6. Plugin Check (wordpress.org's plugin-check), when EFB_PLUGIN_CHECK_WP names a WordPress folder that has the
#    plugin-check plugin: installs the zip there (never into a symlinked or git-managed plugin folder) and writes
#    dist/plugin-check-<version>.csv with the error and warning counts. Set EFB_WPCLI to the WP-CLI command (default
#    wp). Without it the step is skipped and says so.
#
# Needs: git, rsync, zip, unzip, php, node + npm (terser and csso go to EFB_BUILD_TOOLS, default
# ~/.cache/efb-build-tools, never into the repository), and for step 2 curl and python3. The zip is never committed
# (dist/ is git-ignored). Release steps: docs/release/RELEASE.en.md.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REF=HEAD
FETCH=1
UPDATE_LOCK=0
PLUGIN_CHECK=1
while [ $# -gt 0 ]; do
  case "$1" in
    --ref) REF="$2"; shift 2 ;;
    --skip-fetch) FETCH=0; shift ;;
    --update-asset-lock) UPDATE_LOCK=1; shift ;;
    --no-plugin-check) PLUGIN_CHECK=0; shift ;;
    -h|--help) sed -n '2,32p' "$0"; exit 0 ;;
    *) echo "unknown option: $1" >&2; exit 2 ;;
  esac
done

SLUG=easy-form-builder
TERSER_VERSION=5.36.0
CSSO_VERSION=5.0.5
TOOLS="${EFB_BUILD_TOOLS:-$HOME/.cache/efb-build-tools}"
LOCK="$ROOT/bin/public-assets.lock.sha256"
DIST="$ROOT/dist"
WORK="$DIST/build"
STAGE="$WORK/$SLUG"
export LC_ALL=C TZ=UTC

say() { printf '%s\n' "$*"; }
die() { printf 'build-zip: %s\n' "$*" >&2; exit 1; }

cd "$ROOT"
COMMIT="$(git rev-parse --verify "$REF^{commit}")" || die "no commit $REF"
EPOCH="$(git log -1 --format=%ct "$COMMIT")"
VERSION="$(git show "$COMMIT:emsfb.php" | sed -n 's/^[ *]*Version:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' | head -1)"
[ -n "$VERSION" ] || die "no Version in emsfb.php at $COMMIT"
STABLE="$(git show "$COMMIT:readme.txt" | sed -n 's/^Stable tag:[[:space:]]*\([^[:space:]]*\).*/\1/p' | head -1)"
[ "$STABLE" = "$VERSION" ] || die "readme.txt Stable tag ($STABLE) is not the plugin Version ($VERSION)"
ZIP="$DIST/$SLUG-$VERSION.zip"
say "Easy Form Builder $VERSION from $COMMIT ($(git log -1 --format=%cI "$COMMIT"))"

if [ "$REF" = HEAD ]; then
  dirty="$(git status --porcelain --untracked-files=no -- . ':!tests' ':!docs' ':!bin' | sed 's/^/    /' || true)"
  [ -z "$dirty" ] || say "  note: these uncommitted changes are NOT in the zip (it packs $COMMIT):" "$dirty"
fi

# 1. The committed files, minus .distignore.
rm -rf "$WORK"
mkdir -p "$WORK/archive" "$STAGE"
git archive --format=tar "$COMMIT" | tar -x -C "$WORK/archive"
[ -f "$WORK/archive/.distignore" ] || die ".distignore is not committed at $COMMIT"
rsync -a --exclude-from="$WORK/archive/.distignore" "$WORK/archive/" "$STAGE/"
rm -rf "$WORK/archive"
rm -f "$STAGE/public/assets/js/jquery.js" # the stub is gone from git; never ship an older one (Plugin Check: library_core_files)

# 2. The pinned third-party assets, checked against the lock.
if [ "$FETCH" = 1 ]; then
  say "fetching the pinned public assets (bin/fetch-public-assets.sh)"
  bash "$ROOT/bin/fetch-public-assets.sh" "$STAGE"
  # The plugin asks for a list by code (json/cites/<country>/<state code>.json, lower case). A few files of the dataset
  # are copies ("mah (1).json") or named after the state ("la vega.json"): no code reaches them, and Plugin Check
  # refuses their names (badly_named_files). They stay out of the release.
  # (Spaces and ASCII punctuation only: "nö.json" is the list of the state code NÖ and stays.)
  odd="$(cd "$STAGE" && find public/assets/json/geo -type f | python3 -I -c 'import re, sys; [print(l, end="") for l in sys.stdin if re.search(r"[ !-,:-@\[-^`{-~]", l.rsplit("/", 1)[-1].rstrip("\n"))]' | sort)"
  if [ -n "$odd" ]; then
    printf '%s\n' "$odd" | while IFS= read -r f; do rm -f "$STAGE/$f"; done
    say "  left out $(printf '%s\n' "$odd" | wc -l) list files no code requests (copies, or named after the state): $(printf '%s\n' "$odd" | sed 's#.*/json/##' | paste -sd',' | sed 's/,/, /g')"
  fi
  # The fonts README says when it was fetched; the build dates it by the commit so the zip does not change by day.
  sed -i "s/^\(Fetched by bin\/fetch-public-assets.sh on \)[0-9-]*\./\1$(date -u -d "@$EPOCH" +%Y-%m-%d)./" "$STAGE/public/assets/fonts/README.txt"
  ( cd "$STAGE" && find public/assets/fonts public/assets/json/geo public/assets/js/vendor/chart.umd.min.js -type f -print0 | sort -z | xargs -0 sha256sum ) > "$STAGE/public/assets/fetched-assets.sha256"
  # The lock lists each font file and Chart.js; the lists (thousands of files of one pinned git commit) are one line.
  lock_view() {
    grep -v '  public/assets/json/geo/' "$1" || true
    local geo; geo="$(grep '  public/assets/json/geo/' "$1" || true)"
    [ -z "$geo" ] || printf '%s  public/assets/json/geo/ (%s files)\n' "$(printf '%s\n' "$geo" | sha256sum | cut -d' ' -f1)" "$(printf '%s\n' "$geo" | wc -l)"
  }
  lock_view "$STAGE/public/assets/fetched-assets.sha256" > "$WORK/fetched.lock"
  if [ "$UPDATE_LOCK" = 1 ]; then
    cp "$WORK/fetched.lock" "$LOCK"
    say "  wrote $(wc -l < "$LOCK") lines to bin/public-assets.lock.sha256 (review and commit it)"
  elif [ ! -f "$LOCK" ]; then
    die "no bin/public-assets.lock.sha256: run once with --update-asset-lock, review the files, commit the lock"
  elif ! diff -q "$LOCK" "$WORK/fetched.lock" >/dev/null; then
    diff "$LOCK" "$WORK/fetched.lock" | head -20 >&2 || true
    die "the fetched assets differ from bin/public-assets.lock.sha256 (an upstream file changed); review, then --update-asset-lock"
  else
    say "  the $(wc -l < "$STAGE/public/assets/fetched-assets.sha256") fetched files match bin/public-assets.lock.sha256"
  fi
else
  say "  --skip-fetch: no bundled fonts, lists or Chart.js (development zip, not for release)"
fi

# 3. The minified twins.
if [ ! -f "$TOOLS/node_modules/terser/package.json" ] || [ ! -f "$TOOLS/node_modules/csso/package.json" ] \
  || [ "$(node -p "require('$TOOLS/node_modules/terser/package.json').version")" != "$TERSER_VERSION" ] \
  || [ "$(node -p "require('$TOOLS/node_modules/csso/package.json').version")" != "$CSSO_VERSION" ]; then
  say "installing terser $TERSER_VERSION and csso $CSSO_VERSION into $TOOLS"
  mkdir -p "$TOOLS"
  npm install --silent --no-save --no-audit --no-fund --prefix "$TOOLS" "terser@$TERSER_VERSION" "csso@$CSSO_VERSION" >/dev/null
fi
mapfile -t ASSETS < <(
  { grep -rhoE "asset_efb\('[^']+\.(js|css)'\)" "$STAGE/includes" --include='*.php' | sed -E "s/^asset_efb\('(.*)'\)$/\1/"
    ( cd "$STAGE" && find public/assets/fonts -name font.css 2>/dev/null ); } | grep -vE '\.min\.(js|css)$' | sort -u)
[ "${#ASSETS[@]}" -gt 20 ] || die "only ${#ASSETS[@]} asset_efb() assets found"
for a in "${ASSETS[@]}"; do [ -f "$STAGE/$a" ] || die "asset_efb('$a') names a file that is not in the release"; done
say "minifying ${#ASSETS[@]} assets that asset_efb() can pick"
node "$ROOT/bin/minify-assets.js" "$TOOLS/node_modules" "$STAGE" "${ASSETS[@]}"

# 4. Checks, then a fixed form.
say "checking the release folder"
for e in "$STAGE"/* "$STAGE"/.[!.]*; do
  [ -e "$e" ] || continue
  case "$(basename "$e")" in
    emsfb.php|index.php|uninstall.php|readme.txt|CHANGELOG.md|includes|public|languages|vendor) ;;
    *) die "unexpected top-level entry in the release: $(basename "$e") (add it to .distignore or to this list)" ;;
  esac
done
for p in tests docs bin node_modules _workspace temp composer autoload.php README.md .git .claude .github public/assets/js/jquery.js; do
  [ ! -e "$STAGE/$p" ] || die "$p is in the release folder"
done
bad="$(cd "$STAGE" && find . -name '*@*' -o -name '.*' -not -name '.' | head -5)"
[ -z "$bad" ] || die "badly named or hidden files: $bad"
n_php=0
while IFS= read -r -d '' f; do
  php -l "$f" >/dev/null 2>&1 || die "PHP syntax error: ${f#$STAGE/}"
  n_php=$((n_php + 1))
done < <(find "$STAGE" -name '*.php' -print0)
for a in "${ASSETS[@]}"; do
  t="${a%.*}.min.${a##*.}"
  [ -s "$STAGE/$t" ] || die "no twin for $a"
  case "$t" in *.js) node --check "$STAGE/$t" || die "the twin $t does not parse" ;; esac
done
say "  $n_php PHP files lint clean; ${#ASSETS[@]} twins present and valid"

find "$STAGE" -type d -exec chmod 755 {} +
find "$STAGE" -type f -exec chmod 644 {} +
find "$STAGE" -exec touch -h -d "@$EPOCH" {} +

mkdir -p "$DIST"
rm -f "$ZIP" "$ZIP.sha256" "$DIST/$SLUG-$VERSION.sha256" "$DIST/$SLUG-$VERSION.manifest.json"
(
  cd "$WORK"
  { find "$SLUG" -type d | sort | sed 's#$#/#'
    find "$SLUG" -type f ! -name '*.min.js' ! -name '*.min.css' | sort
    find "$SLUG" -type f \( -name '*.min.js' -o -name '*.min.css' \) | sort; } > "$WORK/order.txt"
  zip -q -X -9 -@ "$ZIP" < "$WORK/order.txt"
)
unzip -tq "$ZIP" >/dev/null || die "the zip does not test clean"

# 5. Checksums and the manifest.
( cd "$WORK" && find "$SLUG" -type f -print0 | sort -z | xargs -0 sha256sum ) > "$DIST/$SLUG-$VERSION.sha256"
ZIP_SHA="$(sha256sum "$ZIP" | cut -d' ' -f1)"
printf '%s  %s\n' "$ZIP_SHA" "$(basename "$ZIP")" > "$ZIP.sha256"
php -r '
  [$_, $sums, $out, $version, $commit, $epoch, $zip, $zipsha, $terser, $csso, $fetched] = $argv;
  $files = array();
  foreach (file($sums, FILE_IGNORE_NEW_LINES) as $l) {
    [$h, $p] = explode("  ", $l, 2);
    $files[$p] = array("sha256" => $h, "size" => filesize(dirname($sums) . "/build/" . $p));
  }
  $m = array(
    "plugin" => "easy-form-builder", "version" => $version, "commit" => $commit,
    "commit_time" => gmdate("c", (int) $epoch), "zip" => basename($zip), "zip_sha256" => $zipsha,
    "zip_bytes" => filesize($zip), "files" => count($files), "unpacked_bytes" => array_sum(array_column($files, "size")),
    "tools" => array("terser" => $terser, "csso" => $csso), "fetched_assets" => $fetched === "1" ? "bin/public-assets.lock.sha256" : "skipped (development zip)",
    "built_with" => "bin/build-zip.sh", "sha256" => $files,
  );
  file_put_contents($out, json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$DIST/$SLUG-$VERSION.sha256" "$DIST/$SLUG-$VERSION.manifest.json" "$VERSION" "$COMMIT" "$EPOCH" "$ZIP" "$ZIP_SHA" "$TERSER_VERSION" "$CSSO_VERSION" "$FETCH"
say "built $ZIP"
say "  $(unzip -Z1 "$ZIP" | grep -vc '/$') files, $(stat -c %s "$ZIP") bytes, sha256 $ZIP_SHA"
say "  manifest: $DIST/$SLUG-$VERSION.manifest.json (sha256 $(sha256sum "$DIST/$SLUG-$VERSION.manifest.json" | cut -d' ' -f1))"

# 6. Plugin Check.
if [ "$PLUGIN_CHECK" = 1 ] && [ -n "${EFB_PLUGIN_CHECK_WP:-}" ]; then
  WP_DIR="$(cd "$EFB_PLUGIN_CHECK_WP" && pwd)"
  WPCLI="${EFB_WPCLI:-wp}"
  [ -f "$WP_DIR/wp-load.php" ] || die "EFB_PLUGIN_CHECK_WP is not a WordPress folder: $WP_DIR"
  [ -d "$WP_DIR/wp-content/plugins/plugin-check" ] || die "plugin-check is not installed in $WP_DIR"
  target="$WP_DIR/wp-content/plugins/$SLUG"
  if [ -L "$target" ] || [ -e "$target/.git" ]; then die "$target is a link or a git checkout: Plugin Check needs a WordPress of its own"; fi
  rm -rf "$target"
  unzip -q "$ZIP" -d "$WP_DIR/wp-content/plugins/"
  CSV="$DIST/plugin-check-$VERSION.csv"
  say "Plugin Check on the zip ($WP_DIR)"
  # shellcheck disable=SC2086 # EFB_WPCLI may be "php /path/wp-cli.phar"
  $WPCLI --path="$WP_DIR" plugin check "$SLUG" --format=csv --require="$WP_DIR/wp-content/plugins/plugin-check/cli.php" > "$CSV" 2> "$DIST/plugin-check-$VERSION.log" || true
  errors="$(grep -c ',ERROR,' "$CSV" || true)"
  warnings="$(grep -c ',WARNING,' "$CSV" || true)"
  say "  Plugin Check: $errors errors, $warnings warnings ($CSV)"
elif [ "$PLUGIN_CHECK" = 1 ]; then
  say "Plugin Check: skipped (set EFB_PLUGIN_CHECK_WP to a WordPress folder with the plugin-check plugin)"
fi
