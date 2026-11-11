<?php
/**
 * F18 sub-task A: the read-only plan API on top of the one registry (Builder_Api::capabilities(), B3).
 *
 * - efb_current_package_efb() for emsfb_pro -1/0/1/2/3/10 and never writes the option;
 * - efb_plan_chosen_efb(): false for -1, 10 and a missing option;
 * - efb_feature_required_package_efb(): 0 / 3 / 1, unknown key = 1, filter efb_feature_required_package;
 * - efb_feature_available_efb($feature, $package): the optional package argument;
 * - efb_feature_registry_efb() passes through the filter efb_feature_registry;
 * - every F18 feature key the dialog uses is in the registry with a level and a label key;
 * - add-on levels equal addon_required_package_efb() (read from functions.php).
 *
 * Run: php tests/test-plan-gate-registry.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
define('EMSFB_API_VERSION', 1);

$pass = 0; $fail = 0;
function check($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; }
	else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail) : '') . "\n"; }
}

$GLOBALS['opt'] = array();
$GLOBALS['writes'] = array();
$GLOBALS['hooks'] = array();
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opt']) ? $GLOBALS['opt'][$k] : $d; }
function update_option($k, $v) { $GLOBALS['writes'][] = $k; $GLOBALS['opt'][$k] = $v; return true; }
function delete_option($k) { $GLOBALS['writes'][] = $k; unset($GLOBALS['opt'][$k]); return true; }
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = $cb; }
function apply_filters($h, $v) {
	$args = func_get_args();
	array_shift($args);
	if (empty($GLOBALS['hooks'][$h])) return $v;
	foreach ($GLOBALS['hooks'][$h] as $cb) { $args[0] = call_user_func_array($cb, $args); }
	return $args[0];
}
function get_setting_Emsfb($m = 'decoded') { return (object) array('AdnSPF' => 1); }

/* addon_required_package_efb() as functions.php has it (parsed, so the test follows the real table). */
$fn_src = file_get_contents(__DIR__ . '/../includes/functions.php');
preg_match('/static \$tiers = array\(([\s\S]*?)\);/', $fn_src, $m);
preg_match_all("/'(Adn\w+)'\s*=>\s*(\d)/", $m[1], $mm, PREG_SET_ORDER);
$GLOBALS['tiers'] = array();
foreach ($mm as $t) $GLOBALS['tiers'][$t[1]] = (int) $t[2];
preg_match('/function get_all_addon_keys_efb\(\)\{\s*return array\(([\s\S]*?)\);/', $fn_src, $k);
preg_match_all("/'(Adn\w+)'/", $k[1], $kk);
$GLOBALS['addon_keys'] = $kk[1];
class EfbPlanGateFn {
	public function addon_required_package_efb($a) { return isset($GLOBALS['tiers'][$a]) ? $GLOBALS['tiers'][$a] : 1; }
	public function get_all_addon_keys_efb() { return $GLOBALS['addon_keys']; }
}
function get_efbFunction() { return new EfbPlanGateFn(); }

require __DIR__ . '/../includes/class-Emsfb-builder-api.php';

// 1. current package, chosen, no write
$expect = array(-1 => 2, 0 => 0, 1 => 1, 2 => 2, 3 => 3, 10 => 2);
foreach ($expect as $raw => $pkg) {
	$GLOBALS['opt'] = array('emsfb_pro' => $raw);
	$GLOBALS['writes'] = array();
	$got = efb_current_package_efb();
	check("emsfb_pro=$raw: efb_current_package_efb() = $pkg", $got === $pkg, $got);
	check("emsfb_pro=$raw: efb_plan_chosen_efb() = " . (in_array($raw, array(0, 1, 2, 3), true) ? 'true' : 'false'),
		function_exists('efb_plan_chosen_efb') && efb_plan_chosen_efb() === in_array($raw, array(0, 1, 2, 3), true));
	efb_feature_available_efb('field:esign');
	efb_feature_required_package_efb('field:esign');
	check("emsfb_pro=$raw: reading the plan writes no option", count($GLOBALS['writes']) === 0, $GLOBALS['writes']);
}
$GLOBALS['opt'] = array();
check('missing emsfb_pro: package 2, not chosen', efb_current_package_efb() === 2 && function_exists('efb_plan_chosen_efb') && efb_plan_chosen_efb() === false);

// 2. levels
$GLOBALS['opt'] = array('emsfb_pro' => 2);
$lv = function ($k) { return efb_feature_required_package_efb($k); };
check('field:esign = 3 (Free Plus); field:text = 0; field:pdate/ardate/persiaPay = 1', $lv('field:esign') === 3 && $lv('field:text') === 0 && $lv('field:pdate') === 1 && $lv('field:ardate') === 1 && $lv('field:persiaPay') === 1);
check('setting:mobile_layout = 1 (Pro); limit:steps = 3; unknown = 1', $lv('setting:mobile_layout') === 1 && $lv('limit:steps') === 3 && $lv('no:such') === 1);

// 3. F18 keys present, each with a level and a label key
$f18 = array(
	'setting:thank_you_redirect' => 3, 'setting:email_sub' => 3, 'setting:email_noti_type' => 3, 'setting:loading_type' => 3,
	'setting:custom_required_msg' => 3, 'setting:custom_colors' => 3, 'setting:hidden_disabled' => 3, 'setting:mobile_layout' => 1,
	'setting:sms_notifications' => 1, 'setting:telegram_notifications' => 1,
	'setting:reply_attachments' => 3, 'setting:sender_email' => 3, 'setting:weekly_report' => 3, 'setting:email_stats_off' => 1,
	'setting:email_footer' => 3, 'setting:bulk_read' => 3, 'setting:delete_response' => 3, 'setting:payment_keys' => 3,
	'setting:map_key' => 3, 'setting:response_colors' => 3, 'setting:response_captcha' => 3, 'setting:response_upload' => 3,
	'setting:response_download' => 3, 'setting:response_admin_login' => 3, 'template:payment' => 3, 'form:locked_fields' => 3,
	'limit:steps' => 3, 'addon:AdnSPF' => 3, 'addon:AdnSS' => 1,
);
$reg = efb_feature_registry_efb();
$missing = array();
foreach ($f18 as $key => $level) {
	if (!isset($reg[$key]) || $lv($key) !== $level || empty($reg[$key]['label_key'])) $missing[$key] = isset($reg[$key]) ? array($lv($key), isset($reg[$key]['label_key']) ? $reg[$key]['label_key'] : null) : 'absent';
}
check('every F18 feature key is in the registry with its level and a label key (' . count($f18) . ')', count($missing) === 0, $missing);
$no_label = array();
foreach ($reg as $key => $e) if (empty($e['label_key'])) $no_label[] = $key;
check('every registry entry has a label key', count($no_label) === 0, $no_label);

// 4. availability with an explicit package
check('efb_feature_available_efb(field:esign, 3) true, (field:esign, 2) false, (field:esign, 0) false',
	efb_feature_available_efb('field:esign', 3) === true && efb_feature_available_efb('field:esign', 2) === false && efb_feature_available_efb('field:esign', 0) === false);
check('efb_feature_available_efb(setting:mobile_layout, 3) false, (…, 1) true',
	efb_feature_available_efb('setting:mobile_layout', 3) === false && efb_feature_available_efb('setting:mobile_layout', 1) === true);
check('add-on gating: field:stripe on Free Plus needs AdnSPF (installed here) → available; on Free → not',
	efb_feature_available_efb('field:stripe', 3) === true && efb_feature_available_efb('field:stripe', 2) === false);

// 5. filters
add_filter('efb_feature_registry', function ($r) { $r['field:esign']['package'] = 'pro'; $r['x:custom'] = array('package' => 'free_plus', 'label_key' => 'x'); return $r; });
check('filter efb_feature_registry changes the answer', $lv('field:esign') === 1 && $lv('x:custom') === 3);
add_filter('efb_feature_required_package', function ($n, $f) { return $f === 'field:text' ? 3 : $n; });
check('filter efb_feature_required_package still applies', $lv('field:text') === 3);
$GLOBALS['hooks'] = array();

// 6. add-on levels equal addon_required_package_efb()
$bad = array();
foreach ($GLOBALS['addon_keys'] as $code) {
	$want = isset($GLOBALS['tiers'][$code]) ? $GLOBALS['tiers'][$code] : 1;
	if ($lv('addon:' . $code) !== $want) $bad[] = $code;
}
check('every add-on (' . count($GLOBALS['addon_keys']) . ') has the level of addon_required_package_efb()', count($GLOBALS['addon_keys']) >= 20 && count($bad) === 0, $bad);

// 7. registry JSON for the JS parity test
if (in_array('--json', $argv, true)) { fwrite(STDERR, json_encode(\Emsfb\Builder_Api::capabilities(array('package' => 2)))); }

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
