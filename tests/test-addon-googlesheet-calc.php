<?php
/**
 * F04 and the Google Sheets add-on (vendores-efb googlesheet/, AdnGoS): GoogleSheetAddon::build_row_map() writes
 * a formatted number and a calc result as the canonical number (a numeric cell with USER_ENTERED: 1250000.5,
 * -12 without an apostrophe), never the display text (disp); a two-handle slider as "lo..hi" text; the calc
 * field gets its own column; a hostile disp never reaches the sheet. No change in the add-on was needed.
 *
 * Needs the add-on source: vendor/googlesheet (a site copy) or /home/user/vendores-efb/googlesheet, or
 * EFB_GOOGLESHEET_DIR; [SKIP] otherwise. Run: php tests/test-addon-googlesheet-calc.php
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
$rows = array(
	array('id_' => 'n1', 'name' => 'Amount', 'type' => 'number', 'value' => '1250000.5', 'disp' => '۱٬۲۵۰٬۰۰۰٫۵ تومان'),
	array('id_' => 'n2', 'name' => 'Delta', 'type' => 'number', 'value' => '-12', 'disp' => '-12 kg'),
	array('id_' => 'c1', 'name' => 'Total', 'id_ob' => 'c1', 'type' => 'calc', 'value' => '3750.00', 'disp' => '=HYPERLINK("x")'),
	array('id_' => 'r1', 'name' => 'Budget', 'type' => 'range', 'value' => '10..50', 'lo' => '10', 'hi' => '50', 'disp' => '10 – 50'),
);
$out = $m->invoke($addon, array('integration_values' => $rows), array('field_mode' => 'all'));
t('a formatted number: the canonical number (a numeric cell), not the display text', $out['Amount'], '1250000.5');
t('a negative number stays a number (no apostrophe)', $out['Delta'], '-12');
t('a calc result has its own column with the server\'s canonical value; a hostile disp never reaches the sheet', array($out['Total'], strpos(json_encode($out), 'HYPERLINK')), array('3750.00', false));
t('a two-handle slider: "lo..hi"', $out['Budget'], '10..50');

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
