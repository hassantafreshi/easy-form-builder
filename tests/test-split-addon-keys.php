<?php
/**
 * SPLIT §4.2 (subtask A): saving a form while the add-on that owns some of its keys is absent never empties them.
 *  - stored values come back for every key of an absent add-on (the request's value is ignored), with the warning
 *    addon_keys_kept {addon, field_id, keys};
 *  - a new row (nothing stored) does not get the keys (addon_keys_dropped);
 *  - with the add-on active nothing is touched;
 *  - each moved feature: AdnCLC (number format, calc formula, option calc), AdnTSG (esign_mode), AdnADR (postal
 *    mask preset, the maps field, addr_lookup), AdnBEF (row 0 booking, option dateExp), AdnGEO (geo_on);
 *  - a package import drops them with reason addon_inactive (Form_Package::validate's dropped[]);
 *  - the save paths call it (builder update, Create, Form_Package::validate).
 * The real sanitizer runs first (efbFunction::sanitize_obj_msg_efb), as on the site.
 *
 * Run: php tests/test-split-addon-keys.php
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['efb_filters'] = array();
function get_option($k, $d = false) { return $d; }
function apply_filters($h, $v) { $a = func_get_args(); if (isset($GLOBALS['efb_filters'][$h])) foreach ($GLOBALS['efb_filters'][$h] as $cb) $a[1] = call_user_func_array($cb, array_slice($a, 1)); return $a[1]; }
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['efb_filters'][$h][] = $cb; return true; }
function sanitize_text_field($v) { return is_scalar($v) ? trim(strip_tags((string) $v)) : ''; }
function sanitize_email($v) { return is_string($v) ? trim($v) : ''; }
function sanitize_url($v) { return is_string($v) ? $v : ''; }
function sanitize_textarea_field($v) { return is_scalar($v) ? (string) $v : ''; }
function wp_kses_post($v) { return $v; }
function wp_kses($v, $a = array()) { return $v; }
function esc_url_raw($v) { return $v; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }
function wp_cache_delete() { return true; }
function __($s, $d = '') { return $s; }
function esc_html__($s, $d = '') { return $s; }
function get_locale() { return 'en_US'; }
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/class-Emsfb-addon-catalog.php';
require_once dirname(__DIR__) . '/includes/addon-contracts-functions-efb.php';
require_once dirname(__DIR__) . '/includes/class-Emsfb-addon-keys.php';
class Split_Keys_Fn extends efbFunction { public function __construct() {} }
$fn = new Split_Keys_Fn();
use Emsfb\Addon_Keys;

$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n"; $failed++;
}
$row = function ($rows, $id) { foreach ($rows as $r) if (isset($r['id_']) && $r['id_'] === $id) return $r; return null; };
$codes = function ($w) { return array_map(function ($x) { return $x['code'] . ':' . $x['addon'] . ':' . $x['field_id'] . ':' . implode(',', $x['keys']); }, $w); };

$stored = array(
	array('type' => 'form', 'formName' => 'Old', 'booking' => '1', 'geo_on' => '1', 'geo_notice' => 'Your approximate country is detected.'),
	array('id_' => '1', 'type' => 'step', 'step' => '1'),
	array('id_' => 'n1', 'type' => 'number', 'name' => 'Guests', 'num_step' => '0.5', 'num_decimals' => '1', 'num_format' => 'group', 'step' => '1'),
	array('id_' => 'c1', 'type' => 'calc', 'name' => 'Total', 'formula' => '{n1}*2', 'calc_show' => '1', 'calc_as_price' => '1', 'step' => '1'),
	array('id_' => 'r1', 'type' => 'radio', 'name' => 'Room', 'step' => '1'),
	array('id_' => 'o1', 'type' => 'option', 'parent' => 'r1', 'value' => 'A', 'calc' => '40', 'dateExp' => '2026-12-01', 'step' => '1'),
	array('id_' => 's1', 'type' => 'esign', 'name' => 'Sign', 'esign_mode' => 'both', 'esign_font' => 'latin', 'step' => '1'),
	array('id_' => 'z1', 'type' => 'text', 'name' => 'ZIP', 'mask_preset' => 'us.zip', 'addr_lookup' => '1', 'step' => '1'),
	array('id_' => 'm1', 'type' => 'maps', 'name' => 'Where', 'mark' => '1', 'lat' => '35.7', 'step' => '1'),
);
// What a builder (or AutoPilot) without the add-ons sends back: changed or lost keys, plus a new calc row.
$sent = $stored;
$sent[0]['booking'] = '0'; unset($sent[0]['geo_on']);
$sent[2]['num_step'] = '1'; unset($sent[2]['num_format']); $sent[2]['name'] = 'Guests (renamed)';
unset($sent[3]['formula']);
$sent[5]['calc'] = '99'; unset($sent[5]['dateExp']);
$sent[6]['esign_mode'] = 'draw';
$sent[7]['mask_preset'] = '';
$sent[8]['lat'] = '0';
$sent[] = array('id_' => 'c2', 'type' => 'calc', 'name' => 'New', 'formula' => '{n1}+1', 'step' => '1');

Addon_Keys::set_override(array('AdnCLC' => false, 'AdnTSG' => false, 'AdnADR' => false, 'AdnBEF' => false, 'AdnGEO' => false));
$clean = $fn->sanitize_obj_msg_efb($sent);
list($rows, $warn) = Addon_Keys::keep($clean, $stored);
t('AdnCLC absent: number format keys keep the stored values (0.5, group)', array($row($rows, 'n1')['num_step'], $row($rows, 'n1')['num_format'], $row($rows, 'n1')['num_decimals']), array('0.5', 'group', '1'));
t('…a core key on the same field still changes (the label)', $row($rows, 'n1')['name'], 'Guests (renamed)');
t('AdnCLC absent: the calc formula is kept', array($row($rows, 'c1')['formula'], $row($rows, 'c1')['calc_as_price']), array('{n1}*2', '1'));
t('AdnCLC absent: the option calc value is kept', $row($rows, 'o1')['calc'], '40');
t('AdnCLC absent: a new calc row does not get its keys', array_key_exists('formula', $row($rows, 'c2')), false);
t('AdnTSG absent: esign_mode both / esign_font latin kept', array($row($rows, 's1')['esign_mode'], $row($rows, 's1')['esign_font']), array('both', 'latin'));
t('AdnADR absent: the postal preset us.zip kept although the sanitizer or the request emptied it', $row($rows, 'z1')['mask_preset'], 'us.zip');
t('AdnADR absent: addr_lookup kept', $row($rows, 'z1')['addr_lookup'], '1');
t('AdnADR absent: the maps field keeps its stored keys', $row($rows, 'm1')['lat'], '35.7');
t('AdnBEF absent: row 0 booking and option dateExp kept', array($rows[0]['booking'], $row($rows, 'o1')['dateExp']), array('1', '2026-12-01'));
t('AdnGEO absent: geo_on and geo_notice kept', array($rows[0]['geo_on'], $rows[0]['geo_notice']), array('1', 'Your approximate country is detected.'));
$wc = $codes($warn);
t('warnings name each add-on, field and key (addon_keys_kept / addon_keys_dropped)', array(
	in_array('addon_keys_kept:AdnCLC:n1:num_step,num_decimals,num_format', $wc, true) || in_array('addon_keys_kept:AdnCLC:n1:num_step,num_format,num_decimals', $wc, true),
	count(array_filter($wc, function ($x) { return strpos($x, 'addon_keys_kept:AdnCLC:c1:') === 0 && strpos($x, 'formula') !== false; })) === 1,
	in_array('addon_keys_dropped:AdnCLC:c2:formula', $wc, true),
	count(array_filter($wc, function ($x) { return strpos($x, 'addon_keys_kept:AdnBEF::booking') === 0; })) === 1,
	count(array_filter($wc, function ($x) { return strpos($x, 'addon_keys_kept:AdnGEO::') === 0; })) === 1,
), array(true, true, true, true, true));
t('the warning carries the owner\'s sentence with the add-on name', $warn[0]['m'], 'Settings of Booking are kept until the add-on is installed.');

// With the add-ons active nothing is touched.
Addon_Keys::set_override(array('AdnCLC' => true, 'AdnTSG' => true, 'AdnADR' => true, 'AdnBEF' => true, 'AdnGEO' => true));
list($rows2, $warn2) = Addon_Keys::keep($clean, $stored);
t('all add-ons active: rows unchanged, no warning', array($rows2 === $clean, $warn2), array(true, array()));

// Only one absent.
Addon_Keys::set_override(array('AdnCLC' => true, 'AdnTSG' => false, 'AdnADR' => true, 'AdnBEF' => true, 'AdnGEO' => true));
list($rows3, $warn3) = Addon_Keys::keep($clean, $stored);
t('only AdnTSG absent: the number keys follow the request, esign_mode is kept', array($row($rows3, 'n1')['num_step'], $row($rows3, 's1')['esign_mode'], count($warn3)), array('1', 'both', 1));

// A form package import (no stored version): keys of absent add-ons are dropped and reported.
Addon_Keys::set_override(array('AdnCLC' => false, 'AdnTSG' => true, 'AdnADR' => true, 'AdnBEF' => true, 'AdnGEO' => true));
list($imp, $dropped) = Addon_Keys::drop_for_import($stored);
t('import without AdnCLC: formula / num_* / option calc dropped with reason addon_inactive', array(
	array_key_exists('formula', $imp[3]), array_key_exists('num_step', $imp[2]), array_key_exists('calc', $imp[5]),
	count(array_filter($dropped, function ($d) { return $d['reason'] === 'addon_inactive' && $d['addon'] === 'AdnCLC'; })) >= 7,
), array(false, false, false, true));

// Who uses what.
Addon_Keys::set_override(null);
t('used_addons() of the stored form', Addon_Keys::used_addons($stored), array('AdnCLC', 'AdnTSG', 'AdnADR', 'AdnBEF', 'AdnGEO'));
t('used_addons() of a plain form', Addon_Keys::used_addons(array(array('type' => 'form'), array('id_' => 'a', 'type' => 'text', 'mask_preset' => 'ir.mobile'), array('id_' => 'b', 'type' => 'number'))), array());
t('a plain number without F04 keys belongs to core', Addon_Keys::used_addons(array(array('type' => 'form'), array('id_' => 'b', 'type' => 'number', 'milen' => '1', 'mlen' => '9'))), array());
// Real state: no add-on announces typed_signature → absent; core's Calc is not loaded here either.
t('real state: absent when no add-on and no core provider announces the contract', Addon_Keys::active('AdnTSG'), false);
add_filter('efb_addon_contracts', function ($c) { $c['typed_signature']['AdnTSG'] = 1; return $c; });
t('real state: active once the add-on announces typed_signature v1', Addon_Keys::active('AdnTSG'), true);
add_filter('efb_addon_contracts', function ($c) { $c['address']['AdnADR'] = 0.5; return $c; });
t('real state: an add-on with an old contract version is absent', array(Addon_Keys::active('AdnADR'), efb_addon_contracts()['address']['outdated']), array(false, true));
// efb_addon_keys adds an add-on's keys.
add_filter('efb_addon_keys', function ($m) { $m['AdnPRT'] = array('contract' => 'portal_core', 'row0' => array('portal_*')); return $m; });
t('efb_addon_keys: a prefix key of another add-on', Addon_Keys::used_addons(array(array('type' => 'form', 'portal_on' => '1'))), array('AdnPRT'));

// The save paths call it.
$src = function ($f) { return file_get_contents(dirname(__DIR__) . '/' . $f); };
t('builder save (update): Addon_Keys::keep() with the stored rows, after the sanitizer', (bool) preg_match('/sanitize_obj_msg_efb\(\$valp\).*Addon_Keys::keep\(\$valp, is_array\(\$old_rows_efb\)/s', $src('includes/admin/class-Emsfb-admin.php')), true);
t('Create (new form): Addon_Keys::keep() without stored rows', strpos($src('includes/admin/class-Emsfb-create.php'), 'Addon_Keys::keep($valp, null)') !== false, true);
t('Form_Package::validate(): drop_for_import() into dropped[]', strpos($src('includes/class-Emsfb-form-package.php'), 'Addon_Keys::drop_for_import($p[\'rows\'])') !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
