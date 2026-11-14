<?php
/**
 * F10-C and the Google Sheets add-on (vendores-efb googlesheet/, AdnGoS): GoogleSheetAddon::build_row_map() writes a
 * rich textarea's answer (fmt html) as its text: paragraphs and items on their own lines, entities read, no tag
 * (with core's Layout::rich_plain(), and without it); a quote stored as @efb@dq# is a quote; a plain textarea and a
 * value with markup but without fmt are written as before.
 *
 * Needs the add-on source: vendor/googlesheet, /home/user/vendores-efb/googlesheet or EFB_GOOGLESHEET_DIR;
 * [SKIP] otherwise. Run: php tests/test-addon-googlesheet-rich.php
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
function current_time($t) { return '2026-11-14 10:00:00'; }
function sanitize_text_field($s) { return is_scalar($s) ? trim(strip_tags((string) $s)) : ''; }
function wp_strip_all_tags($s) { return trim(strip_tags(preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s))); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function apply_filters($h, $v) { return $v; }
function get_option($k, $d = false) { return $d; }
require $dir . '/class-Emsfb-googlesheet.php';
$addon = (new ReflectionClass('Emsfb\GoogleSheetAddon'))->newInstanceWithoutConstructor();
$m = new ReflectionMethod('Emsfb\GoogleSheetAddon', 'build_row_map');
$m->setAccessible(true);
$rows = array(
	array('id_' => 'st', 'name' => 'Story', 'type' => 'textarea', 'fmt' => 'html', 'value' => '<p>Hi &amp; <b>welcome</b></p><ul><li>one</li><li>two @efb@dq#x@efb@dq#</li></ul><a href=@efb@dq#https://a.test@efb@dq# rel=@efb@dq#nofollow ugc noopener@efb@dq#>link</a>'),
	array('id_' => 'pl', 'name' => 'Plain', 'type' => 'textarea', 'value' => 'line one@n#line two'),
);
$out = $m->invoke($addon, array('integration_values' => $rows), array('field_mode' => 'all'));
t('without core\'s Layout: the rich answer as text (no tag)', strpos($out['Story'], '<') === false && strpos($out['Story'], 'Hi & welcome') !== false && strpos($out['Story'], 'two "x"') !== false, true);
require_once __DIR__ . '/../includes/class-Emsfb-layout.php';
$out = $m->invoke($addon, array('integration_values' => $rows), array('field_mode' => 'all'));
t('with core\'s Layout::rich_plain(): paragraphs and items on their own lines, entities read, the quote a quote', $out['Story'], "Hi & welcome\none\ntwo \"x\"\nlink");
t('a plain textarea: as before', $out['Plain'], 'line one@n#line two');
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
