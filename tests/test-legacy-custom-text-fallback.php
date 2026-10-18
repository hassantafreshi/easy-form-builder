<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Custom dashboard text saved by an older release (3.x "Localization") holds only
 * the keys that release knew. Every key added since read $ac->text->key without a
 * check: a PHP warning per key and a null in efb_var.text, and the settings panel
 * then stopped saving on text[i].replace() (a TypeError in list_form-efb.js).
 *
 * Real WordPress; the partial settings object is served from the object cache,
 * so nothing is written to the database.
 *
 *   cd wp-content/plugins/easy-form-builder && php tests/test-legacy-custom-text-fallback.php
 */
require dirname(__DIR__, 4) . '/wp-load.php';

$passed = 0; $failed = 0;
$check = static function ($ok, $label) use (&$passed, &$failed) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $ok ? $passed++ : $failed++;
};

$warnings = array();
set_error_handler(static function ($no, $str, $file) use (&$warnings) {
    if (strpos($file, 'functions.php') !== false && strpos($str, 'Undefined property') !== false) $warnings[] = $str;
    return false;
}, E_WARNING | E_NOTICE);

$fn = get_efbFunction();
$reset = static function ($settings) use ($fn) {
    \Emsfb::get_setting_Emsfb('_clear_cache');
    wp_cache_flush();
    if ($settings !== null) wp_cache_set('settings:decoded', $settings, 'emsfb');
    $prop = new ReflectionProperty(get_class($fn), 'req_cache');
    $prop->setAccessible(true);
    $prop->setValue(null, array());
};

// Defaults: no custom text at all.
$reset(null);
$defaults = $fn->text_efb(1);
$check(is_array($defaults) && isset($defaults['create'], $defaults['pcAvgLength']), 'default phrases build');

// A 3.x-style custom text object: two old keys only, one of them customised.
$legacy = (object) array(
    'act_local_efb' => true,
    'text'          => (object) array('create' => 'Erstellen (custom)', 'edit' => 'Edit'),
);
$reset($legacy);
$warnings = array();
$text = $fn->text_efb(1);
$check($warnings === array(), 'no undefined-property warnings for keys the old text object lacks (' . count($warnings) . ')');
$check($text['create'] === 'Erstellen (custom)', 'a saved custom phrase is kept');
$check($text['formName'] === $defaults['formName'], 'a key missing from the old object falls back to its default');
$nulls = array_keys(array_filter($text, static function ($v) { return $v === null; }));
$check($nulls === array(), 'no null phrase reaches efb_var.text (' . implode(',', array_slice($nulls, 0, 5)) . ')');
$check(count($text) === count($defaults), 'the full phrase set has the same keys as the defaults');

// Subset reads (front end) take the same path.
$reset($legacy);
$sub = $fn->text_efb(array('create', 'trackNo', 'pleaseWaiting'));
$check($sub['create'] === 'Erstellen (custom)' && is_string($sub['trackNo']) && $sub['trackNo'] !== '', 'subset reads fall back the same way');

// A null stored for a key (what the old panel saved after the TypeError) is treated as missing.
$legacy->text->trackNo = null;
$reset($legacy);
$sub = $fn->text_efb(array('trackNo'));
$check($sub['trackNo'] === $defaults['trackNo'], 'a stored null falls back to the default');

// An empty custom string is still the admin's choice.
$legacy->text->trackNo = '';
$reset($legacy);
$sub = $fn->text_efb(array('trackNo'));
$check($sub['trackNo'] === '', 'an empty custom phrase is kept as saved');

$reset(null);
restore_error_handler();

// The settings panel skips non-string values instead of throwing.
$js = file_get_contents(dirname(__DIR__) . '/includes/admin/assets/js/list_form-efb.js');
$check((bool) preg_match('/for\(let i in text\)\{\s*if \(typeof text\[i\] !== \'string\'\) continue;/', $js), 'list_form-efb.js skips non-string phrases before .replace()');

echo "\nRESULTS: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
