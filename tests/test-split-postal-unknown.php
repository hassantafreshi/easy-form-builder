<?php
/**
 * SPLIT §10 (subtask E): postal formats and the map field without the Address & Postal add-on (AdnADR).
 *  - a stored postal preset (us.zip, ir.postal_code…) is unknown to core: the field is a plain text box, the value is
 *    stored as typed (only the length rule), the form is never locked; the builder / AutoPilot check reports
 *    unknown_mask as a warning naming AdnADR, never as an error; the save keeps the stored id (Addon_Keys);
 *  - with the add-on the preset comes back (efb_mask_presets) and the value is checked and stored canonical;
 *  - the map field: the add-on notice in its place, never required; core sends no request to unpkg.com and has no
 *    alert() with a translated phrase (SPLIT §20 item 3); the add-on bundles Leaflet 1.7.1 and Leaflet.fullscreen
 *    1.6.0, versioned.
 *
 * Run: php tests/test-split-postal-unknown.php
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n"; $failed++;
}
require_once __DIR__ . '/../includes/class-Emsfb-masks.php';
require_once __DIR__ . '/../includes/class-Emsfb-addon-keys.php';
use Emsfb\Masks;

$zip = array('id_' => 'z', 'type' => 'text', 'mask_preset' => 'us.zip', 'milen' => '', 'mlen' => '10');
t('core alone: us.zip is not in the catalogue (140 → 122 presets)', array(Masks::preset('us.zip'), count(Masks::catalog()['masks'])), array(null, 122));
$r = Masks::check_field($zip, '1234 5');
t('core alone: a postal field takes the value as typed (no format rule, never locked)', array($r['ok'], $r['stored']), array(true, '1234 5'));
$v = Masks::validate_field_keys($zip);
t('core alone: unknown_mask is a warning naming AdnADR, not an error', array(isset($v['errors']) ? $v['errors'] : null, isset($v['warnings'][0]) ? array($v['warnings'][0]['code'], $v['warnings'][0]['addon']) : null),
	array(array(), array('unknown_mask', 'AdnADR')));
$v = Masks::validate_field_keys(array('mask_preset' => 'zz.unknown'));
t('…a made-up id still is an error', isset($v['errors'][0]['code']) ? $v['errors'][0]['code'] : null, 'unknown_mask');
t('the save keeps the stored postal id while the add-on is absent (Addon_Keys)', \Emsfb\Addon_Keys::used_addons(array(array('type' => 'form'), $zip)), array('AdnADR'));

// With the add-on: its efb_mask_presets filter.
$dir = getenv('EFB_ADDRESS_DIR') ?: __DIR__ . '/../vendor/address';
require_once $dir . '/class-Emsfb-address.php';
$GLOBALS['efb_f'] = array('efb_mask_presets' => array('\Emsfb\Address_Efb', 'presets'));
if (!function_exists('apply_filters')) { function apply_filters($h, $v) { return isset($GLOBALS['efb_f'][$h]) ? call_user_func($GLOBALS['efb_f'][$h], $v) : $v; } }
Masks::reset();
t('with the add-on: the 18 postal presets are back (140)', array(is_array(Masks::preset('us.zip')), is_array(Masks::preset('ir.postal_code')), count(Masks::catalog()['masks'])), array(true, true, 140));
$r = Masks::check_field($zip, 'AB-12');
t('with the add-on: the ZIP rule applies again', $r['ok'], false);
$r = Masks::check_field($zip, '90210');
t('…and a valid ZIP is stored', array($r['ok'], $r['stored']), array(true, '90210'));

// The map field.
$fb = file_get_contents(__DIR__ . '/../includes/class-Emsfb-formbuilder.php');
$i = strpos($fb, "case 'maps':");
t('core renders the map only through efb_render_field; else the add-on notice', strpos(substr($fb, $i, 1600), "apply_filters('efb_render_field', null, 'maps'") !== false && strpos(substr($fb, $i, 1600), "addon_needed_message_efb('AdnADR'") !== false, true);
require_once __DIR__ . '/../includes/class-Emsfb-addon-catalog.php';
require_once __DIR__ . '/../includes/class-Emsfb-split.php';
$rows = \Emsfb\Split::unrequire_absent(array(array('type' => 'form'), array('id_' => 'm', 'type' => 'maps', 'required' => '1'), array('id_' => 'e', 'type' => 'email', 'required' => '1')));
t('without the add-on a required map is not required; other fields keep their rule', array($rows[1]['required'], $rows[2]['required']), array(false, '1'));
$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$fn = file_get_contents(__DIR__ . '/../includes/functions.php');
t('core: no unpkg.com, no wp_remote_head for the map, no alert() with a phrase (§20 item 3)', array(strpos($fn . $pub . $fb, 'https://unpkg.com'), strpos($pub, "alert('OpenStreetMap Error:"), strpos(file_get_contents(__DIR__ . '/../includes/admin/assets/js/pro_els-efb.js'), 'https://unpkg.com')), array(false, false, false));
t('core: no Leaflet loader or map functions in pro_els-efb.js', array(strpos(file_get_contents(__DIR__ . '/../includes/admin/assets/js/pro_els-efb.js'), 'function efbCreateMap'), method_exists('\Emsfb\Formbuilder', 'map_search_section_efb')), array(false, false));
$js = file_get_contents($dir . '/assets/js/maps-efb.js');
t('the add-on: Leaflet 1.7.1 and Leaflet.fullscreen 1.6.0 bundled with their licences; maps-efb.js reads them from the add-on', array(
	is_file($dir . '/assets/vendor/leaflet-1.7.1/leaflet.js'), is_file($dir . '/assets/vendor/leaflet-1.7.1/LICENSE'), is_file($dir . '/assets/vendor/leaflet-fullscreen-1.6.0/Control.FullScreen.js'),
	is_file($dir . '/assets/vendor/leaflet-fullscreen-1.6.0/LICENSE'), strpos($js, 'https://unpkg.com'), strpos($js, 'efb_adr_assets') !== false, \Emsfb\Address_Efb::LEAFLET, \Emsfb\Address_Efb::FULLSCREEN),
	array(true, true, true, true, false, true, '1.7.1', '1.6.0'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
