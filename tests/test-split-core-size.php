<?php
/**
 * SPLIT §14 / subtask L: the core stays smaller than before SPLIT, and what left it does not come back.
 *
 * Measured the way SPLIT §3.2 measures it: the git-tracked files a release ships (includes, public, languages,
 * composer, emsfb.php, autoload.php, index.php, readme.txt), byte for byte; languages are counted apart (the packs do
 * not change with SPLIT). Before SPLIT (commit ff8f21ad): 15,250,772 bytes in 287 files, 12,684,732 without
 * languages. After SPLIT (subtask L): 14,741,142 bytes in 270 files, 12,175,102 without languages (−509,630, −4.0 %
 * of the code), deflate-9 about 4.60 MB (was 4.80 MB).
 *
 * The guard: the code (without languages) stays at least 400,000 bytes under the size before SPLIT, the files that
 * moved to an add-on are not in core, and the per-request PHP is smaller (no Calc class, the public class under
 * 1,000,000 bytes). New features may grow the core; this test only fails when SPLIT's gain is eaten (raise the
 * budget on purpose, with the reason, if a feature needs it).
 *
 * Run: php tests/test-split-core-size.php
 */
$root = dirname(__DIR__);
$passed = 0; $failed = 0;
function t($name, $ok, $info = '') {
	global $passed, $failed;
	if ($ok) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}" . ($info !== '' ? ": {$info}" : '') . "\n"; $failed++;
}

const EFB_SPLIT_BEFORE_CODE = 12684732;   // ff8f21ad, without languages
const EFB_SPLIT_AFTER_CODE  = 12175102;   // subtask L, without languages
// The core keeps at least this much of SPLIT's gain. Raised on purpose for F21 (2026-11-15): the Pro renewal reminder
// (includes/class-Emsfb-renewal-reminder.php, its wiring, readme) adds 58,695 bytes, and the core was 5,333 bytes
// under the old 400,000 budget at 45010e04 (12,279,399 → 12,338,094 bytes without languages).
// Raised on purpose for F16 (2026-11-15): accessibility and inline validation are free and in core (spec §14), with the
// F20 P21 page contract: public/assets/js/inline-efb.js (EFBInline), the builder's a11y-builder-efb.js, Emsfb\A11y and
// its autofill labels, the phrases and the runtime in core-efb.js add about 75 KB of code (12,338,094 → about
// 12,415,000 bytes without languages); the core stays well under the size before SPLIT.
// Raised on purpose for F17 (2026-11-18): the gate was already 6,995 bytes over at 68ae5b70 (F12b-L's settings screens,
// 12,441,727 bytes). F17 is core by the owner's SPLIT decision (name formats, the address formats of eleven countries,
// their checks and the P22 display contract; search and postal masks are AdnADR's): Emsfb\Address_Schemes, its JSON
// definition, EFBNameAddr (public), the builder's chooser and group settings, the P22 functions, the hooks in the
// renderer / submit / viewer / CSV / e-mail and 77 phrases add about 125 KB (→ about 12,567,000 bytes without
// languages). The core stays more than 100 KB under the size before SPLIT; the moved files stay out (checked below).
const EFB_SPLIT_MIN_GAIN    = 100000;

$paths = array('includes', 'public', 'languages', 'composer', 'emsfb.php', 'autoload.php', 'index.php', 'readme.txt');
$files = array();
$git = @shell_exec('cd ' . escapeshellarg($root) . ' && git ls-files -- ' . implode(' ', array_map('escapeshellarg', $paths)) . ' 2>/dev/null');
if (is_string($git) && trim($git) !== '') {
	$files = array_values(array_filter(explode("\n", trim($git)), function ($f) use ($root) { return is_file($root . '/' . $f); }));
	$how = 'git ls-files';
} else {
	// Not a git checkout (a release folder): every file under the same paths.
	foreach ($paths as $p) {
		if (is_file("$root/$p")) { $files[] = $p; continue; }
		if (!is_dir("$root/$p")) continue;
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$p", FilesystemIterator::SKIP_DOTS));
		foreach ($it as $f) if ($f->isFile()) $files[] = substr($f->getPathname(), strlen($root) + 1);
	}
	$how = 'folder walk';
}
$total = 0; $lang = 0;
foreach ($files as $f) {
	$n = filesize($root . '/' . $f);
	$total += $n;
	if (strpos($f, 'languages/') === 0) $lang += $n;
}
$code = $total - $lang;
echo "measured ({$how}): " . count($files) . " files, {$total} bytes, {$code} without languages\n";
t('core files found (the measure works)', count($files) > 100 && $code > 5000000, (string) count($files));
t('the code is at least ' . EFB_SPLIT_MIN_GAIN . ' bytes under the size before SPLIT (' . EFB_SPLIT_BEFORE_CODE . ')', $code <= EFB_SPLIT_BEFORE_CODE - EFB_SPLIT_MIN_GAIN, "{$code} bytes; budget " . (EFB_SPLIT_BEFORE_CODE - EFB_SPLIT_MIN_GAIN));
t('the measured gain of subtask L is recorded (after < before)', EFB_SPLIT_AFTER_CODE < EFB_SPLIT_BEFORE_CODE - EFB_SPLIT_MIN_GAIN);

$gone = array(
	'includes/class-Emsfb-calc.php', 'public/assets/js/calc-efb.js', 'public/assets/css/calc-efb.css', 'includes/admin/assets/js/calc-builder-efb.js',
	'public/assets/js/stripe_pay-efb.js', 'public/assets/js/persia_pay-efb.js', 'includes/importers', 'public/assets/fonts',
);
$back = array();
foreach ($gone as $g) if (file_exists($root . '/' . $g)) $back[] = $g;
t('what moved to an add-on is not in core (calc, Stripe / PersiaPay scripts, typed-signature fonts, the CF7 importer)', !$back, implode(', ', $back));
$pub = filesize($root . '/includes/class-Emsfb-public.php');
t('the public class (read on every form request) is under 1,000,000 bytes (1,018,598 before SPLIT)', $pub < 1000000, (string) $pub);
$src = file_get_contents($root . '/includes/class-Emsfb.php');
t('no request reads a Calc class from core (it is the add-on\'s)', strpos($src, "class-Emsfb-calc.php'") === false && strpos($src, 'class-Emsfb-calc.php"') === false);
$req = file_get_contents($root . '/includes/functions.php') . file_get_contents($root . '/includes/class-Emsfb-public.php') . file_get_contents($root . '/includes/class-Emsfb-formbuilder.php');
t('no Leaflet / unpkg.com request from core (the map is the Address & Postal add-on\'s)', strpos($req, 'https://unpkg.com') === false);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
