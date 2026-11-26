<?php
/**
 * B1 REL-04: a reproducible release zip.
 *  - .distignore leaves out the development folders and files (tests, docs, node_modules, _workspace, bin, dist,
 *    temp, agents' and editors' folders, the unused root Composer copy, the planning notes); /dist/ is git-ignored.
 *  - bin/build-zip.sh packs committed files (git archive), runs bin/fetch-public-assets.sh and checks the result
 *    against bin/public-assets.lock.sha256, writes the minified twin of every asset _Public::asset_efb() can pick with
 *    pinned terser and csso, and writes the zip, its sha256 and a manifest with each file's sha256.
 *  - The repository has no public/assets/js/jquery.js stub (Plugin Check: library_core_files) and no "@" in a
 *    shipped file name (badly_named_files): intlTelInput's 2x sprites are flags-2x.webp / globe-2x.webp.
 *  - When a zip has been built (dist/easy-form-builder-<version>.zip), its content is checked too: the top level, no
 *    development file, every twin present and after its source, the manifest and the checksums agree.
 *
 * Run: php tests/test-build-zip.php   (no WordPress, no network; build first for the zip checks: bin/build-zip.sh)
 */
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$dist_ignore = (string) @file_get_contents("$root/.distignore");
$lines = array_values(array_filter(array_map('trim', explode("\n", $dist_ignore)), function ($l) { return $l !== '' && $l[0] !== '#'; }));
t('.distignore exists', $dist_ignore !== '');
foreach (array('/tests', '/docs', '/node_modules', '/_workspace', '/bin', '/dist', '/temp', '/.claude', '/.git', '/.github', '/README.md', '/composer', '/autoload.php', '/package.json', '/MOBILE-PRO-FEATURES-IMPLEMENTATION.md', '/addons-as-separate-plugin.md') as $p) {
	t(".distignore leaves out $p", in_array($p, $lines, true));
}
foreach (array('/includes', '/public', '/languages', '/vendor', '/readme.txt', '/CHANGELOG.md', '/emsfb.php', '/index.php') as $p) {
	t(".distignore keeps $p", !in_array($p, $lines, true));
}
t('.gitignore keeps dist/ out of the repository (the zip is never committed)', (bool) preg_match('#^/dist/\s*$#m', (string) @file_get_contents("$root/.gitignore")));

$sh = (string) @file_get_contents("$root/bin/build-zip.sh");
t('bin/build-zip.sh exists and is valid bash', $sh !== '' && trim((string) shell_exec('bash -n ' . escapeshellarg("$root/bin/build-zip.sh") . ' 2>&1')) === '');
t('it packs committed files only (git archive) minus .distignore', strpos($sh, 'git archive --format=tar "$COMMIT"') !== false && strpos($sh, '--exclude-from=') !== false);
t('it runs bin/fetch-public-assets.sh into the release folder and checks the lock', strpos($sh, 'bin/fetch-public-assets.sh" "$STAGE"') !== false && strpos($sh, 'public-assets.lock.sha256') !== false);
t('it pins terser and csso', (bool) preg_match('/TERSER_VERSION=\d+\.\d+\.\d+/', $sh) && (bool) preg_match('/CSSO_VERSION=\d+\.\d+\.\d+/', $sh));
t('it finds the assets by asset_efb() calls and the bundled font.css files', strpos($sh, "asset_efb\\('[^']+\\.(js|css)'\\)") !== false && strpos($sh, '-name font.css') !== false);
t('it writes the zip, its sha256, a sha256 list and a manifest', strpos($sh, '.zip.sha256') !== false && strpos($sh, '.manifest.json') !== false && strpos($sh, '-$VERSION.sha256') !== false);
t('it fixes times, order and permissions (reproducible)', strpos($sh, 'touch -h -d "@$EPOCH"') !== false && strpos($sh, 'zip -q -X') !== false && strpos($sh, 'LC_ALL=C TZ=UTC') !== false);
t('it never installs into a linked or git-managed plugin folder for Plugin Check', strpos($sh, '[ -L "$target" ] || [ -e "$target/.git" ]') !== false);
t('bin/minify-assets.js is valid JavaScript', trim((string) shell_exec('node --check ' . escapeshellarg("$root/bin/minify-assets.js") . ' 2>&1')) === '');
$lock = (string) @file_get_contents("$root/bin/public-assets.lock.sha256");
// A fetched file the lock pins must not depend on the day: the fonts README said "Fetched … on <date>", so every build
// after the lock's day stopped at the lock check (final regression, 2026-11-26).
$fetch = (string) @file_get_contents("$root/bin/fetch-public-assets.sh");
t('bin/fetch-public-assets.sh writes nothing that depends on the day (no date in the pinned files)', $fetch !== '' && !preg_match('/\$\(date\b/', $fetch));
t('bin/public-assets.lock.sha256 pins the fetched fonts, Chart.js and the lists', preg_match_all('/^[0-9a-f]{64}  public\/assets\/fonts\//m', $lock) > 20 && strpos($lock, '  public/assets/js/vendor/chart.umd.min.js') !== false && (bool) preg_match('/^[0-9a-f]{64}  public\/assets\/json\/geo\/ \(\d+ files\)$/m', $lock));

/* The repository itself. */
t('no public/assets/js/jquery.js stub (Plugin Check: library_core_files)', !file_exists("$root/public/assets/js/jquery.js"));
$tracked = array_filter(explode("\n", (string) shell_exec('cd ' . escapeshellarg($root) . ' && git ls-files -- includes public languages emsfb.php readme.txt 2>/dev/null')));
$at = array_values(array_filter($tracked, function ($f) { return strpos(basename($f), '@') !== false || strpos($f, ' ') !== false; }));
t('no shipped file name has "@" or a space (Plugin Check: badly_named_files)', count($tracked) > 100 && !$at, implode(', ', $at));
$iti = (string) @file_get_contents("$root/includes/admin/assets/css/intlTelInput.min-efb.css");
t('intlTelInput uses the renamed 2x sprites, which exist', strpos($iti, 'url("../image/flags-2x.webp")') !== false && strpos($iti, 'url("../image/globe-2x.webp")') !== false && is_file("$root/includes/admin/assets/image/flags-2x.webp") && is_file("$root/includes/admin/assets/image/globe-2x.webp"));

/* The asset_efb() assets the build minifies all exist. */
$assets = array();
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/includes", FilesystemIterator::SKIP_DOTS)) as $f) {
	if (substr($f->getFilename(), -4) !== '.php') continue;
	if (preg_match_all("/asset_efb\\('([^']+\\.(?:js|css))'\\)/", file_get_contents($f->getPathname()), $m)) foreach ($m[1] as $a) if (!preg_match('/\.min\.(js|css)$/', $a)) $assets[$a] = true;
}
$assets = array_keys($assets); sort($assets);
$missing = array_values(array_filter($assets, function ($a) use ($root) { return !is_file("$root/$a"); }));
t('asset_efb() names ' . count($assets) . ' scripts and styles, each in the repository', count($assets) > 20 && !$missing, implode(', ', $missing));

/* A built zip, when there is one. */
preg_match('/^[ *]*Version:\s*([0-9.]+)/m', (string) file_get_contents("$root/emsfb.php"), $vm);
$version = $vm[1] ?? '';
$zip_path = "$root/dist/easy-form-builder-$version.zip";
if (!is_file($zip_path) || !class_exists('ZipArchive')) {
	echo "[SKIP] no dist/easy-form-builder-$version.zip (or no ZipArchive): run bin/build-zip.sh for the zip checks\n";
} else {
	$z = new ZipArchive();
	t('the zip opens', $z->open($zip_path) === true);
	$names = array();
	for ($i = 0; $i < $z->numFiles; $i++) $names[] = $z->getNameIndex($i);
	$files = array_values(array_filter($names, function ($n) { return substr($n, -1) !== '/'; }));
	$top = array();
	foreach ($names as $n) { $p = explode('/', $n); if ($p[0] !== 'easy-form-builder') $top['<outside>'] = true; elseif (isset($p[1]) && $p[1] !== '') $top[$p[1]] = true; }
	$allowed = array('emsfb.php', 'index.php', 'uninstall.php', 'readme.txt', 'CHANGELOG.md', 'includes', 'public', 'languages', 'vendor');
	t('the zip holds one easy-form-builder folder with only the plugin\'s entries', !array_diff(array_keys($top), $allowed), implode(', ', array_diff(array_keys($top), $allowed)));
	$dev = array_values(array_filter($files, function ($n) { return (bool) preg_match('#^easy-form-builder/(tests|docs|bin|node_modules|_workspace|temp|composer|\.)|/\.|README\.md$|/jquery\.js$|@#', $n); }));
	t('no development file, dot file, jquery.js stub or "@" name in the zip', !$dev, implode(', ', array_slice($dev, 0, 5)));
	$pos = array_flip($names);
	$bad = array();
	foreach ($assets as $a) {
		$src = "easy-form-builder/$a";
		$twin = preg_replace('/\.(js|css)$/', '.min.$1', $src);
		if (!isset($pos[$src], $pos[$twin]) || $pos[$twin] < $pos[$src]) $bad[] = $a;
	}
	t('every asset_efb() source has its twin in the zip, written after it', !$bad, implode(', ', $bad));
	t('the bundled fonts, lists and Chart.js are in the zip', (bool) array_filter($files, function ($n) { return strpos($n, 'easy-form-builder/public/assets/fonts/vazirmatn/font.min.css') === 0; }) && in_array('easy-form-builder/public/assets/js/vendor/chart.umd.min.js', $files, true) && in_array('easy-form-builder/public/assets/json/geo/json/countries.json', $files, true));
	$sums = array();
	foreach (file("$root/dist/easy-form-builder-$version.sha256", FILE_IGNORE_NEW_LINES) ?: array() as $l) { $x = explode('  ', $l, 2); if (count($x) === 2) $sums[$x[1]] = $x[0]; }
	$manifest = json_decode((string) @file_get_contents("$root/dist/easy-form-builder-$version.manifest.json"), true);
	t('the sha256 list names every file of the zip', count($sums) === count($files) && !array_diff($files, array_keys($sums)), count($sums) . ' / ' . count($files));
	$wrong = array();
	foreach (array_slice($files, 0, 400) as $n) if (!isset($sums[$n]) || hash('sha256', (string) $z->getFromName($n)) !== $sums[$n]) $wrong[] = $n;
	t('each file\'s sha256 matches its bytes in the zip (first 400 files)', !$wrong, implode(', ', array_slice($wrong, 0, 3)));
	t('the manifest names the version, the commit, the zip\'s sha256 and every file', is_array($manifest) && $manifest['version'] === $version && preg_match('/^[0-9a-f]{40}$/', (string) $manifest['commit']) && $manifest['zip_sha256'] === hash_file('sha256', $zip_path) && count($manifest['sha256']) === count($files));
	t('the .zip.sha256 file agrees', strpos((string) @file_get_contents("$zip_path.sha256"), hash_file('sha256', $zip_path) . '  easy-form-builder-' . $version . '.zip') === 0);
	$z->close();
}

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
