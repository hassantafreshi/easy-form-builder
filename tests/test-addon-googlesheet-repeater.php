<?php
/**
 * F09 and the Google Sheets add-on (vendores-efb googlesheet/, AdnGoS): GoogleSheetAddon::build_row_map() writes a
 * field of repeater rows (rg / ri) into one cell, "row 1 | row 2" in row order (it used to keep only the last
 * row); a checkbox's choices are joined inside their own row ("Bag | Bag, Seat"); the group's own row is its
 * number of rows; a value that would run as a formula stays text; rows without rg are written exactly as before.
 *
 * Needs the add-on source: vendor/googlesheet, /home/user/vendores-efb/googlesheet or EFB_GOOGLESHEET_DIR;
 * [SKIP] otherwise. Run: php tests/test-addon-googlesheet-repeater.php
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
function get_option($k, $d = false) { return $d; }
require $dir . '/class-Emsfb-googlesheet.php';
$addon = (new ReflectionClass('Emsfb\GoogleSheetAddon'))->newInstanceWithoutConstructor();
$m = new ReflectionMethod('Emsfb\GoogleSheetAddon', 'build_row_map');
$m->setAccessible(true);
$rows = array(
	array('id_' => 'g', 'name' => 'Travellers', 'type' => 'repeater', 'value' => '2'),
	array('id_' => 'nm', 'name' => 'Name', 'type' => 'text', 'value' => 'Sara', 'rg' => 'g', 'ri' => '0'),
	array('id_' => 'ex', 'name' => 'Extras', 'type' => 'checkbox', 'id_ob' => 'e1', 'value' => 'Bag', 'rg' => 'g', 'ri' => '0'),
	array('id_' => 'nm', 'name' => 'Name', 'type' => 'text', 'value' => '=HYPERLINK("x")', 'rg' => 'g', 'ri' => '1'),
	array('id_' => 'ex', 'name' => 'Extras', 'type' => 'checkbox', 'id_ob' => 'e1', 'value' => 'Bag', 'rg' => 'g', 'ri' => '1'),
	array('id_' => 'ex', 'name' => 'Extras', 'type' => 'checkbox', 'id_ob' => 'e2', 'value' => 'Seat', 'rg' => 'g', 'ri' => '1'),
	array('id_' => 'n2', 'name' => 'Note', 'type' => 'text', 'value' => 'ok'),
);
$out = $m->invoke($addon, array('integration_values' => $rows), array('field_mode' => 'all'));
t('the group\'s own row: its number of rows', $out['Travellers'], '2');
t('a text child: both rows in one cell, the formula-like value kept as text', $out['Name'], 'Sara | =HYPERLINK("x")');
t('a checkbox child: rows with " | ", choices of a row with ", "', $out['Extras'], 'Bag | Bag, Seat');
t('a field outside the group as before', $out['Note'], 'ok');
$old = $m->invoke($addon, array('integration_values' => array(array('id_' => 'a', 'name' => 'A', 'type' => 'text', 'value' => 'x'), array('id_' => 'a', 'name' => 'A', 'type' => 'text', 'value' => 'y'))), array('field_mode' => 'all'));
t('rows without rg: written as before (the last row of a field)', $old['A'], 'y');
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
