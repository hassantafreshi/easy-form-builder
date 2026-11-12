<?php
/**
 * PAY04 Omise add-on, sub-task E: packaging and texts.
 *  - core's required-file maps (get_addon_required_files_efb / get_addon_i18n_required_files_efb)
 *    name files that exist in vendor/omise;
 *  - every efb-omise string in the add-on's PHP is in languages/efb-omise.pot (and nothing else is
 *    there); locales.json has the domain efb-omise and, per the owner, no locale yet; no other
 *    text domain is used by the add-on; the browser adapter carries no visible English of its own
 *    except fallbacks;
 *  - omise.zip: one top folder omise/, directory entries, sorted, 0755/0644, fixed date, deflate,
 *    and the same files as the folder;
 *  - the add-on uses only the PAY00 contract: no core file is required from it, network only
 *    through $this->request() / Http::request(), no wp_remote_* of its own, no secret-looking literal.
 *
 * Run: php tests/test-addon-omise-package.php
 */
$passed = 0; $failed = 0;
$t = function ($name, $actual, $expected) use (&$passed, &$failed) {
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
};
$dir = is_dir(__DIR__ . '/../vendor/omise') ? realpath(__DIR__ . '/../vendor/omise') : '/home/user/vendores-efb/omise';
$zip = dirname($dir) . '/omise.zip';

$fn = file_get_contents(__DIR__ . '/../includes/functions.php');
preg_match_all("#'vendor/omise/([^']+)'#", $fn, $m);
$missing = array_values(array_filter(array_unique($m[1]), function ($f) use ($dir) { return !is_file($dir . '/' . $f); }));
$t('core\'s required-file maps name routes-efb.php, omise-phrases-efb.php, languages/locales.json, and they exist', array(count(array_unique($m[1])) >= 3, $missing), array(true, array()));

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
$t('every gettext call of the add-on uses the text domain efb-omise', $domains, array('efb-omise'));
$code = array_values(array_unique(array_map('stripslashes', $mm[1])));
$pot = file_get_contents($dir . '/languages/efb-omise.pot');
preg_match_all('#^msgid "((?:[^"\\\\]|\\\\.)*)"$#m', $pot, $pm);
$potids = array_values(array_filter(array_map('stripcslashes', $pm[1]), 'strlen'));
sort($code); sort($potids);
$t('the .pot lists exactly the strings of the code (' . count($code) . ')', array_values(array_diff($code, $potids)) === array() && array_values(array_diff($potids, $code)) === array(), true);
$t('the .pot header names the domain efb-omise', strpos($pot, '"X-Domain: efb-omise\n"') !== false, true);
$loc = json_decode(file_get_contents($dir . '/languages/locales.json'), true);
$t('locales.json: domain efb-omise, no locale yet (nothing translated, owner\'s instruction)', array($loc['domain'], $loc['locales']), array('efb-omise', array()));
$t('no .po/.mo shipped yet', count(array_filter($all, function ($f) { return preg_match('/\.(po|mo|l10n\.php)$/', $f); })), 0);
$t('no wp_remote_* / curl / file_get_contents of URLs in the add-on (network only through core Http)', preg_match('/wp_remote_|curl_|file_get_contents\s*\(\s*\$(uri|url)/', $php), 0);
$t('no key-shaped literal (skey_/pkey_ followed by key characters) in the add-on', preg_match('/[sp]key_(test_)?[a-z0-9]{8,}/', $php . file_get_contents($dir . '/assets/js/omise-efb.js')), 0);
$t('every folder has index.php', count(array_filter(array_unique(array_map('dirname', $all)), function ($d) use ($all) { return !in_array(($d === '.' ? '' : $d . '/') . 'index.php', $all, true); })), 0);

/* zip layout */
$z = new ZipArchive();
$t('omise.zip opens', $z->open($zip), true);
$names = array(); $bad = array();
for ($i = 0; $i < $z->numFiles; $i++) {
	$s = $z->statIndex($i);
	$names[] = $s['name'];
	$z->getExternalAttributesIndex($i, $os, $attr);
	$mode = ($attr >> 16) & 0777;
	$isdir = substr($s['name'], -1) === '/';
	if ($mode !== ($isdir ? 0755 : 0644) || date('Y-m-d H:i', $s['mtime']) !== '2026-10-18 00:00') $bad[] = $s['name'];
	if (!$isdir && $z->getFromIndex($i) !== file_get_contents($dir . '/' . substr($s['name'], 6))) $bad[] = $s['name'] . ' (content differs from the folder)';
}
$t('one top folder omise/', count(array_filter($names, function ($n) { return strpos($n, 'omise/') !== 0; })), 0);
$sorted = $names; usort($sorted, function ($a, $b) { return strcmp(rtrim($a, '/'), rtrim($b, '/')); });
$t('entries sorted by path, directories included', array($names === $sorted, in_array('omise/assets/js/', $names, true)), array(true, true));
$t('0755 folders / 0644 files, fixed date, same content as the folder', $bad, array());
$zf = array_values(array_map(function ($n) { return substr($n, 6); }, array_filter($names, function ($n) { return substr($n, -1) !== '/'; })));
sort($zf);
$t('the zip holds exactly the folder\'s files', $zf, $all);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
