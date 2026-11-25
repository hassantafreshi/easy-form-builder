<?php
/**
 * SEC-05 (B6): the option emsfb_settings, a copy of the settings row that WordPress autoloaded on every request, held
 * the secrets in plain text (reCAPTCHA secret keys, Stripe / PayPal secret keys until their add-ons move them, the
 * activation code). Emsfb::settings_option_efb() writes the copy with every set secret as "••••••" and autoload off;
 * every writer of the option goes through it; the one reader of a secret in it (the Pro check's activation code)
 * reads the settings row instead. A copy without secrets stays byte for byte the row (the fresh-install check).
 *
 * Run: php tests/test-sec05-settings-option.php
 */
$root = dirname(__DIR__);
$passed = 0; $failed = 0;
function t($name, $ok, $info = '') {
	global $passed, $failed;
	if ($ok) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}" . ($info !== '' ? ": {$info}" : '') . "\n"; $failed++;
}
$GLOBALS['opt'] = array(); $GLOBALS['autoload_calls'] = array();
function update_option($k, $v, $a = null) { $same = isset($GLOBALS['opt'][$k]) && $GLOBALS['opt'][$k][0] === $v; if ($same) return false; $GLOBALS['opt'][$k] = array($v, $a); return true; }
function wp_set_option_autoload($k, $a) { $GLOBALS['autoload_calls'][] = array($k, $a); if (isset($GLOBALS['opt'][$k])) $GLOBALS['opt'][$k][1] = $a; return true; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function apply_filters($h, $v) { return $v; }

$src = (string) file_get_contents("$root/includes/class-Emsfb.php");
$ok = preg_match('/    public static function settings_option_efb\(\$json\) \{.*?\n    \}\n/s', $src, $m);
t('Emsfb::settings_option_efb() exists', $ok === 1);
if ($ok) {
	eval('class EfbSec05Fixture { ' . $m[0] . ' }');
	$row = json_encode(array('secretKey' => '6Lc_secret_value_1234', 'siteKey' => '6Lc_site_key_public', 'stripeSKey' => 'sk_test_PLACEHOLDER_KEY', 'activeCode' => 'ABCDEF-123456', 'emailSupporter' => 'owner@example.test', 'rc3SecretKey' => '', 'smtp' => '1'), JSON_UNESCAPED_UNICODE);
	EfbSec05Fixture::settings_option_efb($row);
	$copy = json_decode($GLOBALS['opt']['emsfb_settings'][0], true);
	t('the copy has no secret: reCAPTCHA, Stripe and the activation code are "••••••"', $copy['secretKey'] === '••••••' && $copy['stripeSKey'] === '••••••' && $copy['activeCode'] === '••••••' && strpos($GLOBALS['opt']['emsfb_settings'][0], 'sk_test') === false, $GLOBALS['opt']['emsfb_settings'][0]);
	t('… public values and empty secrets are kept as they are', $copy['siteKey'] === '6Lc_site_key_public' && $copy['emailSupporter'] === 'owner@example.test' && $copy['rc3SecretKey'] === '' && $copy['smtp'] === '1');
	t('… and the option is not autoloaded', $GLOBALS['opt']['emsfb_settings'][1] === false);
	t('a saved key still reads as saved to an older reader (6 or more characters)', preg_match('/"stripeSKey"\s*:\s*"([^"\\\\]{6,})/', $GLOBALS['opt']['emsfb_settings'][0]) === 1);
	$plain = '{"siteKey":"","secretKey":"","emailSupporter":"","femail":"no-reply@example.test"}';
	$GLOBALS['opt'] = array();
	EfbSec05Fixture::settings_option_efb($plain);
	t('a row without secrets is copied byte for byte', $GLOBALS['opt']['emsfb_settings'][0] === $plain);
	$GLOBALS['opt']['emsfb_settings'][1] = 'yes'; $GLOBALS['autoload_calls'] = array();
	EfbSec05Fixture::settings_option_efb($plain);
	t('an unchanged copy left autoloaded by an older version is switched to autoload off', $GLOBALS['autoload_calls'] === array(array('emsfb_settings', false)) && $GLOBALS['opt']['emsfb_settings'][1] === false);
}
$all = '';
foreach (array('includes/class-Emsfb.php', 'includes/class-Emsfb-install.php', 'includes/functions.php', 'includes/class-Emsfb-public.php', 'includes/admin/class-Emsfb-admin.php') as $f) $all .= (string) file_get_contents("$root/$f");
$bad = array();
foreach (explode("\n", $all) as $line) if (strpos($line, "update_option('emsfb_settings'") !== false && strpos($line, 'update_option(\'emsfb_settings\', $json, false)') === false && strpos($line, "settings_option_efb(") === false) $bad[] = trim($line);
t('every writer of the option goes through settings_option_efb()', !$bad, implode(' | ', $bad));
$fn = (string) file_get_contents("$root/includes/functions.php");
t('the Pro check reads the activation code from the settings row, not from the option', strpos($fn, "get_option('emsfb_settings' , 'null')") === false);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
