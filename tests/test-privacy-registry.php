<?php
/**
 * F13 sub-task F: the privacy features in the capability registry (AutoPilot B3 / SitePilot
 * contract §4.1, §4.2), which integrations read instead of a hand-kept list.
 *
 *  - setting:privacy_ip / _retention / _nostore / _requests: free, available on every plan
 *    (legal requirements are not behind a plan), with their form keys and label keys;
 *  - setting:privacy_site: scope site, the emsfb_setting keys it covers;
 *  - field:<type>.pii_default mirrors Privacy::row_is_personal() (email yes, radio no, display
 *    types null);
 *  - every label key is a real phrase of text_efb();
 *  - no secret or setting value in the registry.
 *
 * Run: php tests/test-privacy-registry.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
define('EMSFB_API_VERSION', 1);
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$GLOBALS['opt'] = array();
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opt']) ? $GLOBALS['opt'][$k] : $d; }
function apply_filters($h, $v) { return $v; }
function add_filter() {} function add_action() {}
function get_setting_Emsfb($m = 'decoded') { return (object) array('prv_days' => '90', 'secretKey' => 'do-not-leak'); }
function get_efbFunction() { return null; }
require __DIR__ . '/../includes/class-Emsfb-privacy.php';
require __DIR__ . '/../includes/class-Emsfb-builder-api.php';
use Emsfb\Builder_Api;

$want = array(
	'setting:privacy_ip'        => array('prv_ip'),
	'setting:privacy_retention' => array('prv_days'),
	'setting:privacy_nostore'   => array('prv_nostore'),
	'setting:privacy_requests'  => array('prv_id_field', 'prv_pii'),
);
$fn_src = file_get_contents(__DIR__ . '/../includes/functions.php');
foreach (array(2, 0, 3, 1) as $pkg) {
	$caps = Builder_Api::capabilities(array('package' => $pkg, 'settings' => (object) array()));
	$f = $caps['features'];
	foreach ($want as $key => $keys) {
		$e = isset($f[$key]) ? $f[$key] : array();
		t("plan {$pkg}: {$key} is free and available now", array(isset($e['package']) ? $e['package'] : null, isset($e['available_now']) ? $e['available_now'] : null, array_key_exists('reason', $e) ? $e['reason'] : 'x'), array('free', true, null));
		if ($pkg === 2) t("{$key} lists its form keys", isset($e['form_keys']) ? $e['form_keys'] : null, $keys);
	}
}
$caps = Builder_Api::capabilities(array('package' => 2, 'settings' => (object) array()));
$site = $caps['features']['setting:privacy_site'];
t('setting:privacy_site: scope site, free, every global key', array($site['scope'], $site['package'], $site['settings_keys']), array('site', 'free', array('prv_ip_mode', 'prv_days', 'prv_action', 'prv_files', 'prv_nostore_file_days', 'prv_erase_mode')));
$a = $site['settings_keys']; sort($a); $b = array_keys(\Emsfb\Privacy::setting_defaults()); sort($b);
t('the site keys are exactly Privacy::setting_defaults()', $a, $b);
$labels = array();
foreach ($caps['features'] as $k => $e) if (strpos($k, 'privacy') !== false) $labels[$k] = $e['label_key'];
$missing = array_filter($labels, function ($l) use ($fn_src) { return strpos($fn_src, '"' . $l . '" =>') === false; });
t('every privacy label key is a phrase of text_efb()', $missing, array());
$fields = $caps['features'];
t('field:email pii_default true', $fields['field:email']['pii_default'], true);
t('field:textarea pii_default true', $fields['field:textarea']['pii_default'], true);
t('field:radio pii_default false', $fields['field:radio']['pii_default'], false);
t('field:checkbox pii_default false', $fields['field:checkbox']['pii_default'], false);
t('field:html pii_default null (no answer)', array_key_exists('pii_default', $fields['field:html']) ? $fields['field:html']['pii_default'] : 'absent', null);
$diff = array();
foreach ($fields as $k => $e) {
	if (strpos($k, 'field:') !== 0 || $e['pii_default'] === null) continue;
	$type = substr($k, 6);
	if ($e['pii_default'] !== \Emsfb\Privacy::row_is_personal(array('type' => $type))) $diff[] = $type;
}
t('every field pii_default equals what anonymization does', $diff, array());
t('no secret and no setting value in the registry', strpos(json_encode($caps), 'do-not-leak') === false && strpos(json_encode($caps), '"90"') === false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
