<?php
/**
 * SPLIT §4.4 (subtask A): one add-on catalogue (Emsfb\Addon_Catalog) feeds the five lists that used to be kept by
 * hand — get_all_addon_keys_efb(), addon_required_package_efb(), get_addon_required_files_efb(),
 * Emsfb_Addon_Compatibility::addon_requirements() and the builder's JS mirror (efb_addon_required_package_efb()).
 *  - the old keys keep their order, plans and files (pinned to the lists of ff8f21ad);
 *  - the SPLIT add-ons are in it with their plans (AdnCLC 3, AdnTSG 3, AdnADR 0, AdnBEF 1, AdnGEO 3);
 *  - efb_addon_catalog adds an add-on everywhere at once;
 *  - the JS mirror equals the PHP table for every key;
 *  - the loader reads vendor/<dir>/routes-efb.php only when the flag is on, PHP is compatible and the plan allows it
 *    (a grandfathered site counts: open question 2).
 *
 * Run: php tests/test-split-catalog.php
 */
define('ABSPATH', __DIR__ . '/');
$opts = array();
$GLOBALS['efb_filters'] = array();
function get_option($k, $d = false) { global $opts; return array_key_exists($k, $opts) ? $opts[$k] : $d; }
function update_option($k, $v, $a = null) { global $opts; $opts[$k] = $v; return true; }
function delete_option($k) { global $opts; unset($opts[$k]); return true; }
function apply_filters($h, $v) { $a = func_get_args(); if (isset($GLOBALS['efb_filters'][$h])) foreach ($GLOBALS['efb_filters'][$h] as $cb) $a[1] = call_user_func_array($cb, array_slice($a, 1)); return $a[1]; }
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['efb_filters'][$h][] = $cb; return true; }
function esc_html__($t, $d = '') { return $t; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function sanitize_text_field($v) { return is_string($v) ? trim(strip_tags($v)) : ''; }
function get_setting_Emsfb($m = 'decoded') { return $GLOBALS['settings_efb']; }
$GLOBALS['settings_efb'] = new stdClass();
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/class-Emsfb-addon-compatibility.php';
class Split_Catalog_Fn extends efbFunction { public function __construct() {} }
$fn = new Split_Catalog_Fn();
function get_efbFunction() { global $fn; return $fn; }

$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n"; $failed++;
}

// The lists of ff8f21ad (before SPLIT), pinned.
$old_keys = array('AdnSPF', 'AdnOF', 'AdnPPF', 'AdnATC', 'AdnSS', 'AdnCPF', 'AdnESZ', 'AdnSE', 'AdnWHS', 'AdnPAP', 'AdnWSP', 'AdnSMF', 'AdnPLF', 'AdnMSF', 'AdnBEF', 'AdnPDP', 'AdnADP', 'AdnATF', 'AdnTLG', 'AdnGoS', 'AdnHSH', 'AdnMOL', 'AdnPPG', 'AdnTAP', 'AdnOMS', 'AdnPTB', 'AdnPMB', 'AdnXND');
$old_tiers = array('AdnOF' => 0, 'AdnSMF' => 3, 'AdnSPF' => 3, 'AdnPAP' => 3, 'AdnMOL' => 3, 'AdnPPG' => 3, 'AdnTAP' => 3, 'AdnOMS' => 3, 'AdnPTB' => 3, 'AdnPMB' => 3, 'AdnXND' => 3);
$old_files = array(
	'AdnSPF' => array('vendor/stripe/class-Emsfb-stripe-payment.php'),
	'AdnOF'  => array('vendor/offline/json/countries.js', 'vendor/offline/json/states/ir.json', 'vendor/offline/json/states/zw.json', 'vendor/offline/json/cites/ir/teh.json', 'vendor/offline/json/cites/zw/mbn.json'),
	'AdnPPF' => array('vendor/persiapay/zarinpal.php'), 'AdnSS' => array('vendor/smssended/smsefb.php'), 'AdnPDP' => array('vendor/persiadatepicker/persiandate.php'),
	'AdnADP' => array('vendor/arabicdatepicker/arabicdate.php'), 'AdnPAP' => array('vendor/paypal/paypalefb.php'), 'AdnTLG' => array('vendor/telegram/telegram-new-efb.php'),
	'AdnATF' => array('vendor/autofill/autofillefb.php'), 'AdnGoS' => array('vendor/googlesheet/class-Emsfb-googlesheet.php'), 'AdnSMF' => array('vendor/logic/logic/class-Emsfb-logic-validator.php'),
	'AdnHSH' => array('vendor/human-shield/human-shield-efb.php'), 'AdnMOL' => array('vendor/mollie/routes-efb.php'), 'AdnPPG' => array('vendor/payplug/routes-efb.php'),
	'AdnTAP' => array('vendor/tappay/routes-efb.php'), 'AdnOMS' => array('vendor/omise/routes-efb.php'), 'AdnPTB' => array('vendor/paytabs/routes-efb.php'),
	'AdnPMB' => array('vendor/paymob/routes-efb.php'), 'AdnXND' => array('vendor/xendit/routes-efb.php'),
);

$keys = $fn->get_all_addon_keys_efb();
t('the old keys keep their order', array_slice($keys, 0, count($old_keys)), $old_keys);
// F12b: the five CAPTCHA add-ons come after the SPLIT ones (codes approved by owner 2026-11-14).
t('the SPLIT add-ons are appended (then the F12b CAPTCHA add-ons)', array_slice($keys, count($old_keys)), array('AdnCLC', 'AdnTSG', 'AdnADR', 'AdnGEO', 'AdnCTS', 'AdnHCP', 'AdnALT', 'AdnQCP', 'AdnFRC'));
// Portal contract 1.1 (owner, 2026-11-19): the customer portal is the separate, free plugin EFB Customer Portal on every
// plan, not an add-on: no AdnPRT entry, so no plan gate and no "buy Pro" card for it (capabilities.portal_core.plugin).
t('portal contract 1.1: no AdnPRT in the catalogue (the free plugin EFB Customer Portal), so no plan of its own', array(\Emsfb\Addon_Catalog::get('AdnPRT'), in_array('AdnPRT', $keys, true)), array(null, false));
$ok = true;
foreach ($old_keys as $k) { $want = isset($old_tiers[$k]) ? $old_tiers[$k] : 1; if ($fn->addon_required_package_efb($k) !== $want) { $ok = false; echo "  plan of {$k} changed\n"; } }
t('every old add-on keeps its plan', $ok, true);
t('SPLIT plans: AdnCLC Free Plus, AdnTSG Free Plus, AdnADR every plan, AdnBEF Pro, AdnGEO Free Plus',
	array_map(array($fn, 'addon_required_package_efb'), array('AdnCLC', 'AdnTSG', 'AdnADR', 'AdnBEF', 'AdnGEO')), array(3, 3, 0, 1, 3));
t('an unknown key is Pro', $fn->addon_required_package_efb('AdnZZZ'), 1);
$files = $fn->get_addon_required_files_efb();
$same = true;
foreach ($old_files as $k => $f) if (!isset($files[$k]) || $files[$k] !== $f) { $same = false; echo "  files of {$k} changed\n"; }
t('every old add-on keeps its required files', $same, true);
t('the SPLIT add-ons are proved by their routes-efb.php',
	array($files['AdnCLC'], $files['AdnTSG'], $files['AdnADR'], $files['AdnBEF'], $files['AdnGEO']),
	array(array('vendor/calc/routes-efb.php'), array('vendor/typed-signature/routes-efb.php'), array('vendor/address/routes-efb.php'), array('vendor/booking/routes-efb.php'), array('vendor/geo/routes-efb.php')));
$req = Emsfb_Addon_Compatibility::addon_requirements();
t('the compatibility list names every catalogue key', array_values(array_diff($keys, array_keys($req))), array());
t('…with the catalogue name for a SPLIT add-on', $req['AdnCLC']['name'], 'Numbers & Calculations');
$i18n = $fn->get_addon_i18n_required_files_efb();
t('the SPLIT add-ons have their own phrase files (efb-<dir>)', array($i18n['AdnCLC'][0], $i18n['AdnGEO'][0]), array('vendor/calc/calc-phrases-efb.php', 'vendor/geo/geo-phrases-efb.php'));

// The filter adds an add-on everywhere at once.
add_filter('efb_addon_catalog', function ($m) { $m['AdnPRT'] = array('dir' => 'portal', 'name' => 'Customer Portal', 'package' => 1, 'contract' => 'portal_core', 'files' => array('vendor/portal/routes-efb.php'), 'loader' => 'routes'); $m['bad key'] = array(); $m['AdnXXX'] = array('dir' => '../etc', 'files' => array('/etc/passwd', 'vendor/../x')); return $m; });
t('efb_addon_catalog: the key list', in_array('AdnPRT', $fn->get_all_addon_keys_efb(), true), true);
t('efb_addon_catalog: the plan', $fn->addon_required_package_efb('AdnPRT'), 1);
t('efb_addon_catalog: the files', $fn->get_addon_required_files_efb()['AdnPRT'], array('vendor/portal/routes-efb.php'));
t('efb_addon_catalog: a malformed key is dropped', in_array('bad key', $fn->get_all_addon_keys_efb(), true), false);
t('efb_addon_catalog: a folder or file outside vendor/ is refused', array(\Emsfb\Addon_Catalog::get('AdnXXX')['dir'], \Emsfb\Addon_Catalog::get('AdnXXX')['files']), array('', array()));
$GLOBALS['efb_filters'] = array();

// The JS mirror equals the table (admin-efb.js efb_addon_required_package_efb with efb_var.addon_catalog).
$src = file_get_contents(dirname(__DIR__) . '/includes/admin/assets/js/admin-efb.js');
preg_match('/function efb_addon_required_package_efb\(item\) \{.*?\n\}\n/s', $src, $m);
$mirror = \Emsfb\Addon_Catalog::js_mirror();
$js = 'var efb_var = {addon_catalog: ' . json_encode($mirror) . '};' . $m[0] . 'var out = {};' . json_encode(array_keys($mirror)) . '.forEach(function(k){ out[k] = efb_addon_required_package_efb(k); });'
	. 'var fb = {}; efb_var = {}; ' . json_encode($old_keys) . '.forEach(function(k){ fb[k] = efb_addon_required_package_efb({name:k, pro:true}); });'
	. 'process.stdout.write(JSON.stringify({out: out, fb: fb}));';
$tmp = tempnam(sys_get_temp_dir(), 'efbsplit') . '.js';
file_put_contents($tmp, $js);
$res = json_decode((string) shell_exec('node ' . escapeshellarg($tmp)), true);
unlink($tmp);
$php = array();
foreach (array_keys($mirror) as $k) $php[$k] = $fn->addon_required_package_efb($k);
t('JS mirror: efb_addon_required_package_efb() equals the PHP table for every key', $res['out'], $php);
$fb_ok = true;
foreach ($old_keys as $k) if ($res['fb'][$k] !== (isset($old_tiers[$k]) ? $old_tiers[$k] : 1)) { $fb_ok = false; echo "  JS fallback of {$k}\n"; }
t('JS mirror without efb_var.addon_catalog (an old page) still answers the old table', $fb_ok, true);
t('admin-efb.js lists the SPLIT add-on cards', count(array_filter(array('AdnCLC', 'AdnTSG', 'AdnADR', 'AdnBEF', 'AdnGEO'), function ($k) use ($src) { return strpos($src, "name:'{$k}'") !== false; })), 5);

// The loader.
function emsfb_is_addon_compatible_efb($k) { return $GLOBALS['compat_efb']; }
$GLOBALS['compat_efb'] = true;
$dir = sys_get_temp_dir() . '/efb-split-cat-' . getmypid();
@mkdir($dir . '/vendor/calc', 0777, true);
@mkdir($dir . '/vendor/address', 0777, true);
file_put_contents($dir . '/vendor/calc/routes-efb.php', '<?php $GLOBALS["loaded_efb"][] = "calc";');
file_put_contents($dir . '/vendor/address/routes-efb.php', '<?php $GLOBALS["loaded_efb"][] = "address";');
$GLOBALS['loaded_efb'] = array();
$opts['emsfb_pro'] = 2;
$s = (object) array('AdnCLC' => 1, 'AdnADR' => 0);
\Emsfb\Addon_Catalog::load($s, $dir);
t('loader: Free site, AdnCLC on (Free Plus add-on) → not loaded; AdnADR off → not loaded', $GLOBALS['loaded_efb'], array());
$s->AdnADR = 1;
\Emsfb\Addon_Catalog::load($s, $dir);
t('loader: AdnADR (every plan) on → loaded', $GLOBALS['loaded_efb'], array('address'));
$opts['emsfb_split_scan'] = array('v' => 1, 'grandfather' => array('AdnCLC'));
\Emsfb\Addon_Catalog::load($s, $dir);
t('loader: Free site grandfathered for AdnCLC → loaded once', $GLOBALS['loaded_efb'], array('address', 'calc'));
\Emsfb\Addon_Catalog::reset_loaded(); $GLOBALS['loaded_efb'] = array(); $GLOBALS['compat_efb'] = false;
\Emsfb\Addon_Catalog::load($s, $dir);
t('loader: PHP not compatible → nothing loaded', $GLOBALS['loaded_efb'], array());
$GLOBALS['compat_efb'] = true; unset($opts['emsfb_split_scan']); $opts['emsfb_pro'] = 3;
\Emsfb\Addon_Catalog::reset_loaded();
\Emsfb\Addon_Catalog::load($s, $dir);
t('loader: Free Plus site → both loaded (Addon_Catalog::loaded())', array_keys(\Emsfb\Addon_Catalog::loaded()), array('AdnCLC', 'AdnADR'));
t('grandfather survives a downgrade (addon_keys_allowed_by_package_efb)', array(in_array('AdnCLC', $fn->addon_keys_allowed_by_package_efb(2), true), ($opts['emsfb_split_scan'] = array('grandfather' => array('AdnCLC'))) && in_array('AdnCLC', $fn->addon_keys_allowed_by_package_efb(2), true)), array(false, true));
array_map('unlink', glob($dir . '/vendor/*/routes-efb.php'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
