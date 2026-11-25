<?php
/**
 * QA4-005: an add-on whose flag is on but whose files are gone (a core update or a reinstall replaced vendor/ while the
 * add-on server was unreachable) is missing ("Needs the … add-on · Install"), not outdated ("Update … to keep using it
 * with this version"). Split::addon_state() and Addon_Catalog::state() answer 'addon' then, and the payment field's
 * notice says the add-on is missing and must be installed again. With the files there, an old add-on is still 'update'.
 *
 * Run: php tests/test-split-missing-addon-files.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_URL', 'https://site.test/wp-content/plugins/easy-form-builder/');
define('EMSFB_PLUGIN_VERSION', '4.3.2');
$root = sys_get_temp_dir() . '/efb-qa4005-' . getmypid() . '/';
@mkdir($root, 0777, true);
define('EMSFB_PLUGIN_DIRECTORY', $root);
register_shutdown_function(function () use ($root) { if (is_dir($root)) shell_exec('rm -rf ' . escapeshellarg($root)); });

$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
efb_pay_test_load();
if (!function_exists('esc_html__')) { function esc_html__($s, $d = '') { return $s; } }
if (!function_exists('esc_attr')) { function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_url')) { function esc_url($s) { return (string) $s; } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.test/wp-admin/' . $p; } }
if (!function_exists('current_user_can')) { function current_user_can($c) { return true; } }
function emsfb_is_addon_compatible_efb($k) { return true; }
function get_setting_Emsfb($m = 'decoded') { return $GLOBALS['settings_efb']; }
$GLOBALS['settings_efb'] = (object) array('AdnSPF' => 1, 'AdnATF' => 1);
require_once __DIR__ . '/../includes/class-Emsfb-addon-catalog.php';
require_once __DIR__ . '/../includes/addon-contracts-functions-efb.php';
require_once __DIR__ . '/../includes/class-Emsfb-addon-keys.php';
require_once __DIR__ . '/../includes/class-Emsfb-builder-api.php';
require_once __DIR__ . '/../includes/class-Emsfb-split.php';
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a) { return 'n'; } }
if (!function_exists('is_rtl')) { function is_rtl() { return false; } }
if (!function_exists('number_format_i18n')) { function number_format_i18n($n, $d = 0) { return number_format((float) $n, $d); } }
require_once __DIR__ . '/../includes/class-Emsfb-formbuilder.php';
$t = 'efb_pay_t';

$fb = (new ReflectionClass('\Emsfb\Formbuilder'))->newInstanceWithoutConstructor();
$fb->valj_efb = array((object) array('type' => 'payment', 'currency' => 'usd'));
$ui = new ReflectionMethod('\Emsfb\Formbuilder', 'legacy_gateway_ui_efb');
$ui->setAccessible(true);
$texts = array('payAmount' => 'Amount', 'payNow' => 'Pay now', 'payment' => 'Payment');

// 1. Stripe on, vendor/stripe gone (the state QA-4 reached after a core update with the add-on server down).
$t('files absent: Addon_Catalog::files_present(AdnSPF) is false', \Emsfb\Addon_Catalog::files_present('AdnSPF'), false);
$t('files absent: Split::addon_state(AdnSPF) is "addon" (missing, Install), not "update"', \Emsfb\Split::addon_state('AdnSPF'), 'addon');
$html = $ui->invoke($fb, 'stripe', (object) array('id_' => 'pp', 'type' => 'stripe'), 'rnd1', '7', $texts);
$t('files absent: the payment field says the add-on is missing and to install it again', array(strpos($html, 'The Stripe Payment add-on of this payment field is missing') !== false, strpos($html, 'Install it again') !== false, strpos($html, 'Update Stripe Payment') === false), array(true, true, true));
$t('files absent: a manual add-on (Auto-Populate) on without its files is "addon" too', \Emsfb\Addon_Catalog::state('AdnATF', $GLOBALS['settings_efb']), 'addon');

// 2. The files are back (an add-on from before SPLIT that does not announce the contract): "update", as before.
@mkdir($root . 'vendor/stripe', 0777, true);
file_put_contents($root . 'vendor/stripe/class-Emsfb-stripe-payment.php', "<?php\n");
$t('files present, no contract: Split::addon_state(AdnSPF) is still "update"', \Emsfb\Split::addon_state('AdnSPF'), 'update');
$html = $ui->invoke($fb, 'stripe', (object) array('id_' => 'pp', 'type' => 'stripe'), 'rnd1', '7', $texts);
$t('files present, no contract: the field says "Update Stripe Payment"', strpos($html, 'Update Stripe Payment to keep using it with this version.') !== false, true);

// 3. Off: "not active", whatever the files.
$GLOBALS['settings_efb']->AdnSPF = 0;
$html = $ui->invoke($fb, 'stripe', (object) array('id_' => 'pp', 'type' => 'stripe'), 'rnd1', '7', $texts);
$t('off: "not active … Activate it"', array(\Emsfb\Split::addon_state('AdnSPF'), strpos($html, 'is not active') !== false), array('addon', true));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
