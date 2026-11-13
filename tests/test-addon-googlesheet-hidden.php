<?php
/**
 * F08 and the Google Sheets add-on (vendores-efb googlesheet/, AdnGoS): GoogleSheetAddon::build_row_map()
 * writes a hidden field's value in its own column (its name), and a value that would run as a formula in a
 * USER_ENTERED append (= + - @, a tab) — a URL parameter in {get:utm_source}, or anything a visitor typed — is
 * kept as text with a leading apostrophe; numbers (-5, +3.2) stay numbers.
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
$rows = array(
	array('id_' => 'h1', 'name' => 'UTM source', 'type' => 'hiddenField', 'value' => 'google'),
	array('id_' => 'h2', 'name' => 'Campaign', 'type' => 'hiddenField', 'value' => '=IMPORTXML("https://evil.test","//a")'),
	array('id_' => 'h3', 'name' => 'Ref', 'type' => 'hiddenField', 'value' => '@SUM(1)'),
	array('id_' => 'h4', 'name' => 'Plus', 'type' => 'hiddenField', 'value' => '+HYPERLINK("x")'),
	array('id_' => 'n1', 'name' => 'Count', 'type' => 'number', 'value' => '-5'),
	array('id_' => 'n2', 'name' => 'Score', 'type' => 'text', 'value' => '+3.2'),
	array('id_' => 't1', 'name' => 'Note', 'type' => 'text', 'value' => '-minus text'),
	array('id_' => 't2', 'name' => 'Tab', 'type' => 'text', 'value' => "\tx"),
	array('id_' => 't3', 'name' => 'Plain', 'type' => 'text', 'value' => 'a=b'),
);
$out = $m->invoke($addon, array('integration_values' => $rows), array('field_mode' => 'all'));
t('a hidden value in its own column', $out['UTM source'], 'google');
t('a formula from a URL parameter is kept as text', $out['Campaign'], "'=IMPORTXML(\"https://evil.test\",\"//a\")");
t('@ and + formulas as text', array($out['Ref'], $out['Plus']), array("'@SUM(1)", "'+HYPERLINK(\"x\")"));
t('numbers stay numbers', array($out['Count'], $out['Score']), array('-5', '+3.2'));
t('a typed "-" text and a tab are kept as text', array($out['Note'], $out['Tab']), array("'-minus text", "'\tx"));
t('other values unchanged', $out['Plain'], 'a=b');
$out2 = $m->invoke($addon, array('integration_values' => $rows), array('field_mode' => 'selected', 'selected_fields' => array('UTM source', 'Campaign')));
t('a hidden column can be chosen like any field', array_keys($out2), array('Submitted At', 'UTM source', 'Campaign'));

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
