<?php
/**
 * F15-D / P23 and the Google Sheets add-on (vendores-efb googlesheet/, AdnGoS): its extras block of the core form
 * package (efb_form_export_extras / efb_form_import_extras, docs/form-package-contract.en.md).
 *  - export: the mapping (field mode, fields, column map, template, tab) without the connection and the spreadsheet;
 *    on a duplicate / a restored version those come along; a form without a binding gives no block;
 *  - import from another site: switched off, field ids follow the id map, a notice asks to connect a sheet, the
 *    block is reported applied; a duplicate keeps the connection, the spreadsheet and the switch.
 *
 * Needs the add-on source (vendor/googlesheet, /home/user/vendores-efb/googlesheet or EFB_GOOGLESHEET_DIR); [SKIP]
 * otherwise. Run: php tests/test-addon-googlesheet-package.php
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
$GLOBALS['opt'] = array();
function current_time($t) { return '2026-11-15 10:00:00'; }
function sanitize_text_field($s) { return is_scalar($s) ? trim(strip_tags((string) $s)) : ''; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function apply_filters($h, $v) { return $v; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opt']) ? $GLOBALS['opt'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opt'][$k] = $v; return true; }
function esc_html__($s, $d = null) { return $s; }
function __($s, $d = null) { return $s; }
require $dir . '/class-Emsfb-googlesheet.php';
$g = (new ReflectionClass('Emsfb\GoogleSheetAddon'))->newInstanceWithoutConstructor();
$GLOBALS['opt']['emsfb_googlesheet_form_bindings'] = array(7 => array('enabled' => 1, 'connection_id' => 'conn1', 'spreadsheet_id' => 'SHEET123', 'sheet_tab' => 'Leads', 'field_mode' => 'mapped',
	'selected_fields' => array('a b', 'em'), 'column_map' => array(array('source' => 'a b', 'header' => 'Name', 'enabled' => 1), array('source' => 'em', 'header' => 'Mail', 'enabled' => 1)), 'template' => '', 'updated_at' => 'x'));
$x = $g->package_export_extras(array(), 7, array('context' => 'export'));
t('export: a googlesheet block {v, addon AdnGoS, data}', array($x['googlesheet']['v'], $x['googlesheet']['addon']), array(1, 'AdnGoS'));
t('export: the mapping without the connection and the spreadsheet', array($x['googlesheet']['data']['field_mode'], $x['googlesheet']['data']['sheet_tab'], isset($x['googlesheet']['data']['connection_id']), isset($x['googlesheet']['data']['spreadsheet_id']), count($x['googlesheet']['data']['column_map'])), array('mapped', 'Leads', false, false, 2));
$d = $g->package_export_extras(array(), 7, array('context' => 'duplicate'));
t('duplicate: the connection and the spreadsheet come along', array($d['googlesheet']['data']['connection_id'], $d['googlesheet']['data']['spreadsheet_id']), array('conn1', 'SHEET123'));
t('a form without a binding: no block', $g->package_export_extras(array('other' => 1), 99, array('context' => 'export')), array('other' => 1));
$rep = $g->package_import_extras(array('applied' => array(), 'notices' => array()), 20, $x, array('context' => 'import', 'id_map' => array('a b' => 'newid1234')));
$b = $GLOBALS['opt']['emsfb_googlesheet_form_bindings'][20];
t('import: applied, a notice to connect a sheet', array($rep['applied'], array_column($rep['notices'], 'code')), array(array('googlesheet'), array('googlesheet_connect')));
t('import: switched off, no spreadsheet, the field ids follow the map', array($b['enabled'], $b['spreadsheet_id'], $b['column_map'][0]['source'], $b['selected_fields']), array(0, '', 'newid1234', array('newid1234', 'em')));
$g->package_import_extras(array('applied' => array(), 'notices' => array()), 21, $d, array('context' => 'duplicate', 'id_map' => array()));
$b = $GLOBALS['opt']['emsfb_googlesheet_form_bindings'][21];
t('duplicate: the copy keeps the connection, the spreadsheet and the switch', array($b['enabled'], $b['connection_id'], $b['spreadsheet_id']), array(1, 'conn1', 'SHEET123'));
t('no googlesheet block: nothing changes', $g->package_import_extras(array('applied' => array()), 22, array('sms' => array()), array()), array('applied' => array()));
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
