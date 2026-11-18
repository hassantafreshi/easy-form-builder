<?php
/**
 * F17: the name / address keys through a real save round trip (sanitize_obj_msg_efb(), the path of
 * tests/test-a11y-save-roundtrip.php): cmp_grp, cmp_kind, name_format, name_part, name_strict, addr_scheme, addr_part,
 * addr_cascade, addr_lookup, addr_req_mode survive with allowed values; an addr_part / format outside the lists, a hostile
 * group id, an array, a key on row 0 are dropped; an old form comes back without them. F15: the group id (an id_) is
 * remapped with the field ids on a package import (efb_form_package_remap_ids), so the keys travel.
 *
 * Run: php tests/test-name-address-save-roundtrip.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
$EFB = __DIR__ . '/..';

/* WordPress-faithful stubs for the sanitiser path (arrays become '', as WordPress does). */
function sanitize_text_field($v) {
	if (is_array($v) || is_object($v)) {
		return '';
	}
	$s = strip_tags((string) $v);
	$s = preg_replace('/%[a-f0-9]{2}/i', '', $s);          // WordPress strips URL-encoded octets
	$s = preg_replace('/[\r\n\t ]+/', ' ', $s);            // and collapses whitespace
	return trim($s);
}
function wp_check_invalid_utf8($s) { return preg_match('//u', (string) $s) ? (string) $s : ''; }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function sanitize_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><strong><em><i><br><p><a>'); }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s, $d = null) { return $s; }
function apply_filters($h, $v) { return $v; }
function add_filter() { return true; }
function add_action() { return true; }
function do_action() {}
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }
function wp_cache_delete() { return true; }
function get_option($k, $d = false) { return $d; }
function update_option() { return true; }
function absint($v) { return abs(intval($v)); }
function wp_json_encode($v) { return json_encode($v); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function wp_slash($v) { return is_string($v) ? addslashes($v) : $v; }

require $EFB . '/includes/json-efb.php';
require $EFB . '/includes/class-Emsfb-masks.php';
require $EFB . '/includes/functions.php';
require_once $EFB . '/includes/class-Emsfb-address-schemes.php';

$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n      expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();
function save_and_read($fn, $form) {
	$json = str_replace('\\"', '"', json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	$valp = json_decode(emsfb_json_normalize_efb(wp_unslash(wp_slash($json))), true);
	$valp = $fn->sanitize_obj_msg_efb($valp);
	return json_decode(emsfb_json_normalize_efb(str_replace('"', '\"', json_encode($valp, JSON_UNESCAPED_UNICODE))), true);
}
function fld($id, $extra) { return array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => 'F ' . $id, 'step' => '1', 'amount' => '2', 'required' => '0', 'placeholder' => '', 'value' => ''), $extra); }
$form = array(
	array('type' => 'form', 'steps' => '1', 'formName' => 'N', 'email' => '', 'cmp_grp' => 'n1'),
	array('type' => 'step', 'id_' => '1', 'dataId' => '1-id', 'name' => 'Step 1', 'step' => '1'),
	fld('n1', array('elementId' => 'firstName', 'cmp_grp' => 'n1', 'cmp_kind' => 'name', 'name_format' => 'ar4', 'name_part' => 'first', 'name_strict' => true)),
	fld('n2', array('cmp_grp' => 'n1', 'cmp_kind' => 'name', 'name_format' => 'ar4', 'name_part' => 'grandfather', 'name_strict' => '0')),
	fld('a1', array('type' => 'stateProvince', 'elementId' => 'stateProvince', 'cmp_grp' => 'a1', 'cmp_kind' => 'address', 'addr_scheme' => 'sa', 'addr_part' => 'state', 'addr_lookup' => '1', 'addr_req_mode' => 'sa_short_or_full')),
	fld('a2', array('type' => 'cityList', 'elementId' => 'cityList', 'cmp_grp' => 'a1', 'cmp_kind' => 'address', 'addr_scheme' => 'sa', 'addr_part' => 'city', 'addr_cascade' => 'on')),
	fld('b1', array('type' => 'address_line', 'cmp_grp' => 'x><img src=x onerror=1>', 'cmp_kind' => 'house', 'addr_scheme' => 'mars', 'addr_part' => 'tower', 'addr_req_mode' => 'always', 'name_part' => 'nick')),
	fld('b2', array('type' => 'address_line', 'cmp_grp' => array('x'), 'addr_part' => array('street'), 'addr_cascade' => 'maybe')),
	fld('c1', array('type' => 'address_line', 'elementId' => 'address_line')),
);
$out = save_and_read($fn, $form);
t('the form decodes', is_array($out), true);
t('row 0: a group key there means nothing (dropped)', array_key_exists('cmp_grp', $out[0]), false);
foreach (array('cmp_grp' => 'n1', 'cmp_kind' => 'name', 'name_format' => 'ar4', 'name_part' => 'first', 'name_strict' => '1') as $k => $v) t("name part: $k kept", $out[2][$k] ?? null, $v);
t('name_strict "0" kept (an explicit no)', $out[3]['name_strict'] ?? null, '0');
foreach (array('addr_scheme' => 'sa', 'addr_part' => 'state', 'addr_lookup' => '1', 'addr_req_mode' => 'sa_short_or_full') as $k => $v) t("address part: $k kept", $out[4][$k] ?? null, $v);
t('addr_cascade "on" -> "1"', $out[5]['addr_cascade'] ?? null, '1');
foreach (array('cmp_grp', 'cmp_kind', 'addr_scheme', 'addr_part', 'addr_req_mode', 'name_part') as $k) t("outside the list / hostile: $k dropped", array_key_exists($k, $out[6]), false);
t('arrays dropped; addr_cascade "maybe" -> "0"', array(array_key_exists('cmp_grp', $out[7]), array_key_exists('addr_part', $out[7]), $out[7]['addr_cascade'] ?? null), array(false, false, '0'));
t('an old field comes back without any F17 key', array_intersect(array_keys($out[8]), \Emsfb\Address_Schemes::KEYS), array());
// F15: a package import that remaps ids rewrites cmp_grp with them (core's remap_structural).
require_once $EFB . '/includes/class-Emsfb-form-package.php';
$map = array('n1' => 'zz9', 'a1' => 'yy8');
$re = \Emsfb\Form_Package::remap_structural($out, $map, array());
t('F15 remap: the group id follows its first part (name)', array($re[2]['id_'], $re[2]['cmp_grp'], $re[3]['cmp_grp']), array('zz9', 'zz9', 'zz9'));
t('F15 remap: address group', array($re[4]['cmp_grp'], $re[5]['cmp_grp']), array('yy8', 'yy8'));

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
