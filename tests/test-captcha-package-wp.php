<?php
/**
 * F12b scenario row 26 on a real WordPress: a form's CAPTCHA service and its options travel in the F15 form package,
 * never a key; importing on a site without that add-on keeps them and says so (captcha_addon_missing), and the
 * imported form is protected by the site's Google mode until the add-on is installed.
 * Needs WordPress: EFB_WP_LOAD=/path/to/wp-load.php (else dirname(__DIR__, 4)/wp-load.php; [SKIP] when absent). The
 * ALTCHA add-on (AdnALT) must be switched off on that site (the e2e suite leaves it off).
 *
 * Run: EFB_WP_LOAD=/path/to/site/wp-load.php php tests/test-captcha-package-wp.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) { echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n"; exit(0); }
define('WP_USE_THEMES', false);
require_once $wp_load;
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n"; $failed++;
}
if (\Emsfb\Captcha\Registry::get('altcha')) { echo "[SKIP] the ALTCHA add-on is on here; switch AdnALT off for this test\n\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$admin = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
wp_set_current_user((int) $admin[0]);
$row0 = array('type' => 'form', 'steps' => 1, 'formName' => 'F12b package', 'captcha' => '1', 'captcha_provider' => 'altcha', 'cp_start' => 'onload', 'cp_floating' => '1', 'cp_msg_failed' => 'Try the check again',
	'secretKey' => 'must-never-travel-placeholder', 'rc3SecretKey' => 'must-never-travel-placeholder');
$pkg = array('format' => 'efb-form-package', 'schema' => 1, 'form' => array('form_name' => 'F12b package', 'form_structer' => array(
	$row0, array('id_' => '1', 'type' => 'step', 'step' => '1', 'name' => 'S', 'amount' => 0),
	array('id_' => 'nm', 'dataId' => 'nm-id', 'type' => 'text', 'name' => 'Name', 'step' => '1', 'amount' => 1))));
$v = \Emsfb\Form_Package::validate($pkg);
$codes = array_column($v['notices'], 'code');
$miss = $v['notices'][array_search('captcha_addon_missing', $codes, true)] ?? array();
t('validate(): the service whose add-on is missing is reported with its code (captcha_addon_missing, AdnALT)', array(in_array('captcha_addon_missing', $codes, true), $miss['provider'] ?? null, $miss['addon'] ?? null), array(true, 'altcha', 'AdnALT'));
$imp = \Emsfb\Form_Package::import($pkg);
$fid = is_array($imp) ? (int) $imp['form_id'] : 0;
$rows = $fid ? \Emsfb\Spam::form_rows($fid) : array();
$r0 = $rows ? $rows[0] : array();
t('import(): the form keeps captcha_provider and its cp_* options', array($r0['captcha_provider'] ?? null, $r0['cp_start'] ?? null, $r0['cp_floating'] ?? null, $r0['cp_msg_failed'] ?? null), array('altcha', 'onload', '1', 'Try the check again'));
t('import(): no secret key was stored in the form', array(isset($r0['secretKey']), isset($r0['rc3SecretKey'])), array(false, false));
$e = \Emsfb\Captcha\Registry::effective_for($r0);
t('the imported form asks for ALTCHA and is protected by the fallback until the add-on is installed', array($e['want'], $e['fallback'], $e['why']), array('altcha', true, 'addon'));
$out = $fid ? \Emsfb\Form_Package::export($fid) : null;
$json = wp_json_encode($out);
$f0 = is_array($out) && isset($out['form']['form_structer'][0]) ? $out['form']['form_structer'][0] : array();
t('export(): the package carries captcha_provider and cp_* back, and no secret anywhere', array($f0['captcha_provider'] ?? null, $f0['cp_start'] ?? null, strpos((string) $json, 'must-never-travel') === false), array('altcha', 'onload', true));
if ($fid) \Emsfb\Form_Package::delete($fid, $imp['import_token']);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
