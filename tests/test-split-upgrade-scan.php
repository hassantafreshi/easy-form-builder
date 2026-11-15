<?php
/**
 * SPLIT §4.5 / subtask I: the upgrade scan of an existing site. Every form is read once (200 per run); each SPLIT
 * add-on a form needs is switched on and its download queued through the existing add-on installer (mocked here:
 * Split::$installer stands in for queue_addon_recovery_efb()); a site whose plan is below an add-on it already used
 * is grandfathered (open question 2, approved by owner 2026-11-14) — only on the first scan; AdnGEO is never
 * installed by the scan (it needs the owner's own API key). "Try again" reads the forms again.
 * Also: the forms list data (forms_needing), the notice (bdi around the add-on name, 44 px buttons, plaintext
 * direction), capabilities.split and the forms/{id}/schema entry of a field whose add-on is absent.
 *
 * Run: php tests/test-split-upgrade-scan.php
 */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
$opts = array();
$GLOBALS['efb_filters'] = array();
$GLOBALS['scheduled'] = array();
function get_option($k, $d = false) { global $opts; return array_key_exists($k, $opts) ? $opts[$k] : $d; }
function update_option($k, $v, $a = null) { global $opts; $opts[$k] = $v; return true; }
function delete_option($k) { global $opts; unset($opts[$k]); return true; }
function delete_transient($k) { return true; }
function apply_filters($h, $v) { $a = func_get_args(); if (isset($GLOBALS['efb_filters'][$h])) foreach ($GLOBALS['efb_filters'][$h] as $cb) $a[1] = call_user_func_array($cb, array_slice($a, 1)); return $a[1]; }
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['efb_filters'][$h][] = $cb; return true; }
function add_action($h, $cb, $p = 10, $n = 1) { return true; }
function wp_next_scheduled($h) { return in_array($h, $GLOBALS['scheduled'], true) ? time() : false; }
function wp_schedule_single_event($t, $h, $a = array()) { $GLOBALS['scheduled'][] = $h; return true; }
function __($s, $d = '') { return $s; }
function esc_html__($s, $d = '') { return $s; }
function esc_attr__($s, $d = '') { return $s; }
function _n($a, $b, $n, $d = '') { return $n == 1 ? $a : $b; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return (string) $s; }
function admin_url($p = '') { return 'https://site.test/wp-admin/' . $p; }
function wp_create_nonce($a) { return 'n0nce'; }
function sanitize_text_field($v) { return is_scalar($v) ? trim(strip_tags((string) $v)) : ''; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', $s); }
function get_setting_Emsfb($m = 'decoded') { return $GLOBALS['settings_efb']; }
$GLOBALS['settings_efb'] = (object) array('emailSupporter' => 'owner@site.test');
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/class-Emsfb-addon-catalog.php';
require_once dirname(__DIR__) . '/includes/addon-contracts-functions-efb.php';
require_once dirname(__DIR__) . '/includes/class-Emsfb-addon-keys.php';
require_once dirname(__DIR__) . '/includes/class-Emsfb-builder-api.php';
require_once dirname(__DIR__) . '/includes/class-Emsfb-split.php';
class Split_Scan_Fn extends efbFunction {
	public $saved = 0;
	public function __construct() {}
	public static function set_setting_Emsfb($s, $email = '') { $GLOBALS['settings_efb'] = $s; $GLOBALS['saved_settings']++; return true; }
}
$GLOBALS['saved_settings'] = 0;
$fn = new Split_Scan_Fn();
function get_efbFunction() { global $fn; return $fn; }

// A fake emsfb_form table: 450 forms, a few of them use moved features (stored with escaped quotes, as the site does).
class Split_Fake_Db {
	public $prefix = 'wp_';
	public $forms = array();
	public $queries = 0;
	public function prepare($q) { $a = array_slice(func_get_args(), 1); return vsprintf(str_replace('%d', '%d', $q), $a); }
	public function get_results($q) {
		$this->queries++;
		preg_match('/form_id > (\d+) ORDER BY form_id ASC LIMIT (\d+)/', $q, $m);
		$out = array();
		foreach ($this->forms as $id => $rows) if ($id > (int) $m[1]) { $out[] = (object) array('form_id' => $id, 'form_structer' => str_replace('"', '\\"', json_encode($rows))); if (count($out) >= (int) $m[2]) break; }
		return $out;
	}
}
$wpdb = new Split_Fake_Db();
$GLOBALS['wpdb'] = $wpdb;
for ($i = 1; $i <= 450; $i++) $wpdb->forms[$i] = array(array('type' => 'form'), array('id_' => '1', 'type' => 'step'), array('id_' => 'a' . $i, 'type' => 'text'));
$wpdb->forms[3][] = array('id_' => 'c', 'type' => 'calc', 'formula' => '1+1');
$wpdb->forms[250][] = array('id_' => 'n', 'type' => 'number', 'num_decimals' => '2');
$wpdb->forms[260][] = array('id_' => 's', 'type' => 'esign', 'esign_mode' => 'type');
$wpdb->forms[300][] = array('id_' => 'z', 'type' => 'text', 'mask_preset' => 'de.plz');
$wpdb->forms[410][0]['booking'] = '1';
$wpdb->forms[420][0]['geo_on'] = '1';
$wpdb->forms[430][] = array('id_' => 'p', 'type' => 'stripe');

$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n"; $failed++;
}

$calls = array();
\Emsfb\Split::$installer = function ($ctx) use (&$calls) { $calls[] = $ctx; return array('queued' => true, 'reason' => 'scheduled'); };

// Free site (emsfb_pro 2), first scan after the upgrade.
$opts['emsfb_pro'] = 2;
\Emsfb\Split::maybe_schedule_scan();
t('admin_init schedules the scan once (WP-Cron)', array($GLOBALS['scheduled'], get_option('emsfb_split_scan')['done']), array(array('emsfb_split_scan_efb'), false));
\Emsfb\Split::run_scan_event();
$s = get_option('emsfb_split_scan');
t('one run reads 200 forms and schedules the next', array($s['cursor'], $s['done'], count($GLOBALS['scheduled'])), array(200, false, 2));
\Emsfb\Split::run_scan_event(); \Emsfb\Split::run_scan_event();
$s = get_option('emsfb_split_scan');
t('three runs: every form read (450), done', array($s['cursor'], $s['done'], $wpdb->queries), array(450, true, 3));
t('the forms of each add-on', $s['forms'], array('AdnCLC' => array(3, 250), 'AdnTSG' => array(260), 'AdnADR' => array(300), 'AdnBEF' => array(410), 'AdnGEO' => array(420), 'AdnSPF' => array(430)));
t('Free site: AdnCLC / AdnTSG / AdnBEF used before → grandfathered (first scan); AdnADR is for every plan', $s['grandfather'], array('AdnCLC', 'AdnTSG', 'AdnBEF'));
t('switched on and queued through the installer, once, AdnGEO and the gateway left to the owner', array($s['queued'], count($calls), $calls[0]['addons']), array(array('AdnCLC', 'AdnTSG', 'AdnADR', 'AdnBEF'), 1, array('AdnCLC', 'AdnTSG', 'AdnADR', 'AdnBEF')));
t('the flags are on in the settings and in emsfb_addon_<code>', array($GLOBALS['settings_efb']->AdnCLC, $GLOBALS['settings_efb']->AdnBEF, get_option('emsfb_addon_AdnADR'), isset($GLOBALS['settings_efb']->AdnGEO)), array(1, 1, 1, false));
t('the plan gate now lets this Free site install AdnCLC (grandfathered)', $fn->addon_plan_allows_install_efb('AdnCLC'), true);
t('…but not an add-on it never used (AdnGEO)', $fn->addon_plan_allows_install_efb('AdnGEO'), false);
\Emsfb\Split::maybe_schedule_scan();
t('a finished scan is not scheduled again', count($GLOBALS['scheduled']), 3);

// Forms list / notice: the add-ons are on but no add-on announced its contract yet (files not downloaded).
$need = \Emsfb\Split::forms_needing();
t('forms_needing(): each form with its add-on and why', array(array_keys($need), $need[3][0]['addon'], $need[3][0]['state'], $need[420][0]['addon']), array(array(3, 250, 260, 300, 410, 420, 430), 'AdnCLC', 'addon', 'AdnGEO'));
$html = \Emsfb\Split::admin_notice_html($need);
t('notice: the add-on name is isolated in <bdi>, lines keep their own direction, buttons are 44 px high', array(
	strpos($html, 'Needs the <bdi>Numbers &amp; Calculations</bdi> add-on') !== false,
	substr_count($html, 'unicode-bidi:plaintext') >= 3, strpos($html, 'min-height:44px') !== false, strpos($html, 'role="region"') !== false), array(true, true, true, true));
// An add-on announces itself: its forms leave the list.
add_filter('efb_addon_contracts', function ($c) { $c['calc']['AdnCLC'] = 1; return $c; });
$need = \Emsfb\Split::forms_needing();
t('AdnCLC active → forms 3 and 250 leave the list', array(isset($need[3]), isset($need[250]), isset($need[260])), array(false, false, true));

// Offline: the installer could not queue → "Try again" in the notice; retry() reads again (not a first scan).
$opts['emsfb_split_scan']['install'] = array('queued' => false, 'reason' => 'backoff');
$html = \Emsfb\Split::admin_notice_html(\Emsfb\Split::forms_needing());
t('offline: the notice offers Try again and a manual download link', array(strpos($html, 'efb-split-retry') !== false, strpos($html, 'Download manually') !== false), array(true, true));
$GLOBALS['settings_efb']->AdnTSG = 0; delete_option('emsfb_addon_AdnTSG');
$opts['emsfb_pro'] = 2; $opts['emsfb_split_scan']['grandfather'] = array();
$calls = array();
$s = \Emsfb\Split::retry();
t('Try again: forms read again; AdnTSG is not grandfathered a second time → blocked by plan, not queued', array($s['done'], isset($s['blocked']['AdnTSG']), in_array('AdnTSG', $s['queued'], true), $s['grandfather']), array(true, true, false, array()));

// capabilities.split and the moved entries (a Free Plus site now).
$opts['emsfb_pro'] = 3;
$caps = \Emsfb\Split::capabilities(array('features' => array('field:calc' => array('kind' => 'field', 'package' => 'free_plus', 'addon' => null, 'available_now' => true, 'reason' => null))), array('settings' => $GLOBALS['settings_efb']));
t('capabilities.split: v1, moved map, contracts, add-ons with plan and state', array($caps['split']['v'], $caps['split']['moved']['field:calc'], isset($caps['split']['contracts']['calc']['active']), $caps['split']['addons']['AdnGEO']['state'], $caps['split']['addons']['AdnCLC']['package']), array(1, 'AdnCLC', true, 'addon', 'free_plus'));
t('field:calc names its add-on (AdnCLC active through its contract)', array($caps['features']['field:calc']['addon'], $caps['features']['field:calc']['available_now']), array('AdnCLC', true));
t('setting:smart_geo: available_now false, reason addon', array($caps['features']['setting:smart_geo']['addon'], $caps['features']['setting:smart_geo']['available_now'], $caps['features']['setting:smart_geo']['reason']), array('AdnGEO', false, 'addon'));
t('no secret in capabilities.split', preg_match('/secret|token|apikey|password/i', json_encode($caps['split'])), 0);

// forms/{id}/schema: a field drawn by an absent add-on.
$e = \Emsfb\Split::schema_field(array('id' => 'm', 'type' => 'maps'), array('id_' => 'm', 'type' => 'maps'), 5);
t('schema: maps without AdnADR → assistant none, unavailable {addon, reason}', array($e['assistant'], $e['unavailable']), array('none', array('addon' => 'AdnADR', 'reason' => 'addon')));
$e = \Emsfb\Split::schema_field(array('id' => 'c', 'type' => 'calc'), array('id_' => 'c', 'type' => 'calc'), 5);
t('schema: calc with AdnCLC active → unchanged', isset($e['unavailable']), false);

// A core version that still has the calculation engine (provider "core"): AdnCLC is not downloaded; when a later
// version moves it out, the scan runs again by itself and installs it (grandfathered: the site used it before).
$GLOBALS['efb_filters'] = array();
add_filter('efb_addon_contracts', function ($c) { $c['calc']['core'] = 1; return $c; });
delete_option('emsfb_split_scan'); $GLOBALS['settings_efb'] = (object) array('emailSupporter' => 'owner@site.test'); $opts['emsfb_pro'] = 2; $calls = array(); $GLOBALS['scheduled'] = array();
foreach (array_keys($opts) as $k) if (strpos($k, 'emsfb_addon_') === 0) unset($opts[$k]);
$s = \Emsfb\Split::scan_all();
t('engine still in core: AdnCLC is found but not queued', array(isset($s['forms']['AdnCLC']), in_array('AdnCLC', $s['queued'], true), $s['moved']), array(true, false, array('AdnTSG', 'AdnADR', 'AdnBEF')));
$GLOBALS['efb_filters'] = array();
\Emsfb\Split::maybe_schedule_scan();
t('the engine left core: the finished scan is started again', array(get_option('emsfb_split_scan')['done'], get_option('emsfb_split_scan')['newly']), array(false, array('AdnCLC')));
$s = \Emsfb\Split::scan_all();
t('…and AdnCLC is queued, grandfathered on this Free site', array(in_array('AdnCLC', $s['queued'], true), in_array('AdnCLC', $s['grandfather'], true)), array(true, true));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
