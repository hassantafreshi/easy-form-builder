<?php
/**
 * PAY07 Xendit add-on, sub-task E: packaging and texts.
 *  - core's required-file maps (get_addon_required_files_efb / get_addon_i18n_required_files_efb)
 *    name files that exist in vendor/xendit;
 *  - every efb-xendit string in the add-on's PHP is in languages/efb-xendit.pot (and nothing else is
 *    there); locales.json has the domain efb-xendit and, per the owner, no locale yet; no other
 *    text domain is used by the add-on; the browser adapter carries no visible English of its own
 *    except fallbacks;
 *  - xendit.zip: one top folder xendit/, directory entries, sorted, 0755/0644, fixed date, deflate,
 *    and the same files as the folder;
 *  - the add-on uses only the PAY00 contract: no core file is required from it, network only
 *    through $this->request() / Http::request(), no wp_remote_* of its own, no secret-looking literal,
 *    the webhook token compared with hash_equals; badges are plain local SVGs.
 *
 * Run: php tests/test-addon-xendit-package.php
 */
$passed = 0; $failed = 0;
$t = function ($name, $actual, $expected) use (&$passed, &$failed) {
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
};
$dir = is_dir(__DIR__ . '/../vendor/xendit') ? realpath(__DIR__ . '/../vendor/xendit') : '/home/user/vendores-efb/xendit';
$zip = dirname($dir) . '/xendit.zip';

$fn = file_get_contents(__DIR__ . '/../includes/functions.php');
preg_match_all("#'vendor/xendit/([^']+)'#", $fn, $m);
$missing = array_values(array_filter(array_unique($m[1]), function ($f) use ($dir) { return !is_file($dir . '/' . $f); }));
$t('core\'s required-file maps name routes-efb.php, xendit-phrases-efb.php, languages/locales.json, and they exist', array(count(array_unique($m[1])) >= 3, $missing), array(true, array()));

$php = '';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
$all = array();
foreach ($files as $f) {
	$rel = substr($f->getPathname(), strlen($dir) + 1);
	$all[] = $rel;
	if (substr($rel, -4) === '.php') $php .= file_get_contents($f->getPathname()) . "\n";
}
sort($all);
preg_match_all("#__\(\s*'((?:[^'\\\\]|\\\\.)*)',\s*'([a-z0-9-]+)'\s*\)#", $php, $mm);
$domains = array_values(array_unique($mm[2]));
$t('every gettext call of the add-on uses the text domain efb-xendit', $domains, array('efb-xendit'));
$code = array_values(array_unique(array_map('stripslashes', $mm[1])));
$pot = file_get_contents($dir . '/languages/efb-xendit.pot');
preg_match_all('#^msgid "((?:[^"\\\\]|\\\\.)*)"$#m', $pot, $pm);
$potids = array_values(array_filter(array_map('stripcslashes', $pm[1]), 'strlen'));
sort($code); sort($potids);
$t('the .pot lists exactly the strings of the code (' . count($code) . ')', array_values(array_diff($code, $potids)) === array() && array_values(array_diff($potids, $code)) === array(), true);
$t('the .pot header names the domain efb-xendit', strpos($pot, '"X-Domain: efb-xendit\n"') !== false, true);
$loc = json_decode(file_get_contents($dir . '/languages/locales.json'), true);
$t('locales.json: domain efb-xendit, no locale yet (nothing translated, owner\'s instruction)', array($loc['domain'], $loc['locales']), array('efb-xendit', array()));
$t('no .po/.mo shipped yet', count(array_filter($all, function ($f) { return preg_match('/\.(po|mo|l10n\.php)$/', $f); })), 0);
$t('no wp_remote_* / curl / file_get_contents of URLs in the add-on (network only through core Http)', preg_match('/wp_remote_|curl_|file_get_contents\s*\(\s*\$(uri|url)/', $php), 0);
$t('no key-shaped literal (xnd_development_/xnd_production_ followed by key characters) in the add-on', preg_match('/xnd_(development|production)_[A-Za-z0-9]{8,}/', $php . file_get_contents($dir . '/assets/js/xendit-efb.js')), 0);
$t('the webhook token is compared with hash_equals, never with == / ===', array(strpos($php, 'hash_equals( $stored, $given )') !== false, preg_match('/\\$\\w+\\s*[=!]==?\\s*\\$(token|given|stored)\\b|\\$(token|given|stored)\\s*[=!]==?\\s*\\$/', $php)), array(true, 0));
$t('no core file is required by the add-on (only its own files)', preg_match_all('#require(_once)?\s*\(?\s*__DIR__\s*\.\s*\'/([^\']+)\'#', $php, $rq) && count(array_filter($rq[2], function ($f) use ($dir) { return !is_file($dir . '/' . $f); })) === 0 && strpos($php, 'includes/payments') === false, true);
$t('every badge of the channel list exists and is a small SVG without script', count(array_filter(glob($dir . '/assets/img/*.svg'), function ($f) { $c = file_get_contents($f); return strlen($c) > 2000 || stripos($c, '<script') !== false || stripos($c, 'href') !== false; })), 0);
$t('every folder has index.php', count(array_filter(array_unique(array_map('dirname', $all)), function ($d) use ($all) { return !in_array(($d === '.' ? '' : $d . '/') . 'index.php', $all, true); })), 0);

/* zip layout */
$z = new ZipArchive();
$t('xendit.zip opens', $z->open($zip), true);
$names = array(); $bad = array();
for ($i = 0; $i < $z->numFiles; $i++) {
	$s = $z->statIndex($i);
	$names[] = $s['name'];
	$z->getExternalAttributesIndex($i, $os, $attr);
	$mode = ($attr >> 16) & 0777;
	$isdir = substr($s['name'], -1) === '/';
	if ($mode !== ($isdir ? 0755 : 0644) || date('Y-m-d H:i', $s['mtime']) !== '2026-10-18 00:00') $bad[] = $s['name'];
	if (!$isdir && $z->getFromIndex($i) !== file_get_contents($dir . '/' . substr($s['name'], 7))) $bad[] = $s['name'] . ' (content differs from the folder)';
}
$t('one top folder xendit/', count(array_filter($names, function ($n) { return strpos($n, 'xendit/') !== 0; })), 0);
$sorted = $names; usort($sorted, function ($a, $b) { return strcmp(rtrim($a, '/'), rtrim($b, '/')); });
$t('entries sorted by path, directories included', array($names === $sorted, in_array('xendit/assets/js/', $names, true)), array(true, true));
$t('0755 folders / 0644 files, fixed date, same content as the folder', $bad, array());
$zf = array_values(array_map(function ($n) { return substr($n, 7); }, array_filter($names, function ($n) { return substr($n, -1) !== '/'; })));
sort($zf);
$t('the zip holds exactly the folder\'s files', $zf, $all);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
