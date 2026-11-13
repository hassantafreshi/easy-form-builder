<?php
/**
 * F07 and the Google Sheets add-on (vendores-efb googlesheet/, AdnGoS): GoogleSheetAddon::build_row_map()
 * puts the files of one upload field into one cell joined with " | " (the second file used to overwrite
 * the first), keeps a field of one file as before, and writes a private file (uploads/efb-private/) as the
 * signed, expiring link core makes (Uploads::display_url), never its blocked address.
 *
 * Needs the add-on source: vendor/googlesheet (a site copy) or /home/user/vendores-efb/googlesheet, or
 * EFB_GOOGLESHEET_DIR; [SKIP] otherwise. Run: php tests/test-addon-googlesheet-files.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_DIRECTORY', dirname(__DIR__) . '/');
$dir = '';
foreach (array(getenv('EFB_GOOGLESHEET_DIR') ?: '', __DIR__ . '/../vendor/googlesheet', '/home/user/vendores-efb/googlesheet') as $d) {
	if ($d !== '' && is_readable($d . '/class-Emsfb-googlesheet.php')) { $dir = $d; break; }
}
if ($dir === '') { echo "[SKIP] the Google Sheets add-on source was not found\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n  expected " . json_encode($expected) . "\n  actual   " . json_encode($actual) . "\n";
}
function current_time($t) { return '2026-11-13 10:00:00'; }
function sanitize_text_field($s) { return is_scalar($s) ? trim(strip_tags((string) $s)) : ''; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function apply_filters($h, $v) { return $v; }
function wp_salt($s = 'auth') { return 'unit-salt-' . $s; }
function rest_url($p = '') { return 'https://site.example/wp-json/' . ltrim($p, '/'); }
function add_query_arg($args, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args); }
function wp_upload_dir($t = null, $c = true) { return array('basedir' => '/tmp/efb-uploads', 'baseurl' => 'https://site.example/wp-content/uploads', 'error' => false); }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function wp_normalize_path($p) { return str_replace('\\', '/', $p); }
function wp_basename($p) { return basename($p); }
function get_option($k, $d = false) { return $d; }
require dirname(__DIR__) . '/includes/class-Emsfb-upload-guard.php';
require dirname(__DIR__) . '/includes/class-Emsfb-uploads.php';
require $dir . '/class-Emsfb-googlesheet.php';

$addon = (new ReflectionClass('Emsfb\GoogleSheetAddon'))->newInstanceWithoutConstructor();
$m = new ReflectionMethod('Emsfb\GoogleSheetAddon', 'build_row_map');
$m->setAccessible(true);
$u = 'https://site.example/wp-content/uploads/2026/11/';
$rows = array(
	array('id_' => 'docs', 'name' => 'Documents', 'type' => 'file', 'value' => '@file@', 'url' => $u . 'efb-PLG-261113-AAAAAAAAAAAA.pdf'),
	array('id_' => 'docs', 'name' => 'Documents', 'type' => 'file', 'value' => '@file@', 'url' => $u . 'efb-PLG-261113-BBBBBBBBBBBB.png'),
	array('id_' => 'docs', 'name' => 'Documents', 'type' => 'file', 'value' => '@file@', 'url' => $u . 'efb-PLG-261113-CCCCCCCCCCCC.pdf'),
	array('id_' => 'cv', 'name' => 'CV', 'type' => 'file', 'value' => '@file@', 'url' => $u . 'efb-PLG-261113-DDDDDDDDDDDD.pdf'),
	array('id_' => 'secret', 'name' => 'Private', 'type' => 'file', 'value' => '@file@', 'url' => 'https://site.example/wp-content/uploads/efb-private/2026/11/efb-PLG-261113-EEEEEEEEEEEE.pdf'),
	array('id_' => 'name', 'name' => 'Name', 'type' => 'text', 'value' => 'Sara'),
);
$out = $m->invoke($addon, array('integration_values' => $rows), array('field_mode' => 'all'));
t('three files of one field: one cell, joined with " | "', $out['Documents'], $u . 'efb-PLG-261113-AAAAAAAAAAAA.pdf | ' . $u . 'efb-PLG-261113-BBBBBBBBBBBB.png | ' . $u . 'efb-PLG-261113-CCCCCCCCCCCC.pdf');
t('a field of one file: its address, as before', $out['CV'], $u . 'efb-PLG-261113-DDDDDDDDDDDD.pdf');
t('a private file: a signed link, not the blocked address', array(strpos($out['Private'], 'wp-json/Emsfb/v1/files/signed?') !== false, strpos($out['Private'], '/efb-private/') === false), array(true, true));
t('other answers unchanged', $out['Name'], 'Sara');

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
