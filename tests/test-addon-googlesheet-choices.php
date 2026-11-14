<?php
/**
 * F05 and the Google Sheets add-on (vendores-efb googlesheet/, AdnGoS): GoogleSheetAddon::build_row_map() writes
 * a choice with its label in the field's column and its stored value (ov, written by the server) in "<name> (value)";
 * the choices of one checkbox field share one cell (each row used to overwrite the last); a multiselect is joined
 * with ", ", a ranking with " > "; an "Other" answer reads "Other: text"; a stored value that would run as a formula
 * is kept as text; rows without ov (older forms) are written exactly as before.
 *
 * Needs the add-on source: vendor/googlesheet (a site copy) or /home/user/vendores-efb/googlesheet, or
 * EFB_GOOGLESHEET_DIR; [SKIP] otherwise. Run: php tests/test-addon-googlesheet-choices.php
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
	array('id_' => 'c1', 'name' => 'Langs', 'type' => 'checkbox', 'id_ob' => 'o1', 'value' => 'PHP', 'ov' => 'php'),
	array('id_' => 'c1', 'name' => 'Langs', 'type' => 'checkbox', 'id_ob' => 'o2', 'value' => 'JS', 'ov' => 'js'),
	array('id_' => 'c1', 'name' => 'Langs', 'type' => 'checkbox', 'id_ob' => 'c1_oth', 'value' => 'Rust', 'other' => '1'),
	array('id_' => 'r1', 'name' => 'Province', 'type' => 'radio', 'id_ob' => 'p1', 'value' => 'تهران', 'ov' => 'IR-23'),
	array('id_' => 'm1', 'name' => 'Sizes', 'type' => 'multiselect', 'value' => 'Small@efb!Large@efb!', 'ov' => 's@efb!l@efb!'),
	array('id_' => 'k1', 'name' => 'Rank', 'type' => 'ranking', 'value' => 'C@efb!A@efb!', 'ov' => '=c@efb!a@efb!', 'ids' => 'x3,x1'),
	array('id_' => 'q1', 'name' => 'Old', 'type' => 'radio', 'id_ob' => 'q', 'value' => 'Plain old'),
);
$out = $m->invoke($addon, array('integration_values' => $rows), array('field_mode' => 'all'));
t('the choices of one checkbox field share one cell (labels), Other says so', $out['Langs'], 'PHP, JS, Other: Rust');
t('their stored values in "<name> (value)"; Other is its own text', $out['Langs (value)'], 'php, js, Rust');
t('a radio: label in the column, ISO code beside it', array($out['Province'], $out['Province (value)']), array('تهران', 'IR-23'));
t('a multiselect joined with ", " (both columns)', array($out['Sizes'], $out['Sizes (value)']), array('Small, Large', 's, l'));
t('a ranking with " > "; a stored value that looks like a formula stays text', array($out['Rank'], $out['Rank (value)']), array('C > A', "'=c > a"));
t('an older answer without ov: one column, as before', array($out['Old'], isset($out['Old (value)'])), array('Plain old', false));

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
