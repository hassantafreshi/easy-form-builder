<?php
/**
 * F01-C/H: every mask key survives a real save round trip, exactly as the builder sends it:
 *   JSON.stringify -> builder transport (replace \" -> ") -> wp_unslash(POST) -> emsfb_json_normalize_efb
 *   -> json_decode -> sanitize_obj_msg_efb -> json_encode -> str_replace('"','\"') (stored column)
 *   -> emsfb_json_normalize_efb -> json_decode (every reader).
 * Values outside the allow-lists are reset; old forms without mask keys come back unchanged.
 *
 * Run: php tests/test-masks-save-roundtrip.php
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

$pass = 0;
$fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) {
		$pass++;
		echo "[PASS] $label\n";
		return;
	}
	$fail++;
	echo "[FAIL] $label\n      expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}

$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();

/** The builder's JSON.stringify (no slash or unicode escaping), then its transport replace. */
function builder_payload($form) {
	$json = json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	return str_replace('\\"', '"', $json);           // actionSendData_emsFormBuilder: .replace(/\\\"/g, '"')
}
/** update_form_id_Emsfb + a reader. Returns the decoded rows, or null when the payload is not JSON. */
function save_and_read($fn, $payload) {
	$post = wp_unslash(wp_slash($payload));            // WordPress adds slashes to $_POST; the handler removes them
	$valp = json_decode(emsfb_json_normalize_efb($post), true);
	if (!is_array($valp)) {
		return null;
	}
	$valp = $fn->sanitize_obj_msg_efb($valp);
	$stored = str_replace('"', '\"', json_encode($valp, JSON_UNESCAPED_UNICODE));
	return json_decode(emsfb_json_normalize_efb($stored), true);
}
function form_with($rows) {
	return array_merge(array(
		array('type' => 'form', 'steps' => '1', 'formName' => 'Masks', 'email' => ''),
		array('type' => 'step', 'id_' => '1', 'dataId' => '1-id', 'name' => 'Step 1', 'step' => '1'),
	), $rows);
}
function fld($id, $extra) {
	return array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => 'F ' . $id, 'step' => '1', 'amount' => '2', 'required' => '0', 'placeholder' => '', 'value' => ''), $extra);
}

// 1. Values the builder accepts come back byte for byte.
$keep = array(
	'0\\999 999 9999', 'aa99[ ****]', '9{3,4}', '(999) 999-9999', '\\\\99', '9\\\\9', '99  99', '\\a\\9-999',
	'IR99 9999 9999 9999 9999 9999 99', 'aa99[ ****][ ****][ ****][ ****][ ****][ ****][ ****][ **]', '99/99/9999', 'بب99', 'AA&&-99', 'S\\A99',
);
$rows = array();
foreach ($keep as $i => $m) {
	$rows[] = fld('c' . $i, array('mask_preset' => 'custom', 'mask_custom' => $m, 'mask_checksum' => 'luhn', 'mask_store' => 'display', 'mask_strict' => 1, 'inputmode' => 'numeric', 'mask_case' => 'upper'));
}
$rows[] = fld('p1', array('type' => 'tel', 'elementId' => 'tel', 'mask_preset' => 'ir.mobile', 'mask_store' => 'canonical', 'mask_strict' => '0', 'inputmode' => 'auto'));
$rows[] = fld('p2', array('elementId' => 'postalcode', 'mask_preset' => 'ir.postal_code', 'mask_strict' => true, 'inputmode' => 'tel'));
$rows[] = fld('p3', array('mask_preset' => '', 'inputmode' => 'decimal'));
$out = save_and_read($fn, builder_payload(form_with($rows)));
t('form with masks decodes after the round trip', is_array($out), true);
foreach ($keep as $i => $m) {
	t('mask_custom kept: ' . $m, $out[$i + 2]['mask_custom'], $m);
}
$c0 = $out[2];
t('custom row keys', array($c0['mask_preset'], $c0['mask_checksum'], $c0['mask_store'], $c0['mask_strict'], $c0['inputmode'], $c0['mask_case']), array('custom', 'luhn', 'display', '1', 'numeric', 'upper'));
$n = count($keep) + 2;
t('preset row keys (tel, ir.mobile)', array($out[$n]['mask_preset'], $out[$n]['mask_store'], $out[$n]['mask_strict'], $out[$n]['inputmode']), array('ir.mobile', 'canonical', '0', 'auto'));
t('mask_strict true is stored as "1"', $out[$n + 1]['mask_strict'], '1');
t('empty mask_preset is kept as ""', $out[$n + 2]['mask_preset'], '');
t('inputmode decimal kept', $out[$n + 2]['inputmode'], 'decimal');
t('custom mask still validates after the round trip', \Emsfb\Masks::check_field(array('mask_preset' => 'custom', 'mask_custom' => $out[2]['mask_custom']), '0991 234 5678')['stored'], '09912345678');

// 2. Values the builder refuses are reset.
$bad = array(
	array('mask_preset', '../x', ''), array('mask_preset', 'zz.unknown', ''), array('mask_preset', 'IR.MOBILE', ''),
	array('mask_preset', 'ir.mobile<script>', ''), array('mask_preset', array('ir.mobile'), ''), array('mask_preset', ' ir.mobile ', 'ir.mobile'),
	array('mask_custom', '99 %41', ''), array('mask_custom', '99<b>', ''), array('mask_custom', "99\n99", ''), array('mask_custom', '9{', ''),
	array('mask_custom', str_repeat('9', 65), ''), array('mask_custom', '----', ''), array('mask_custom', array('99'), ''),
	array('mask_checksum', 'bogus', ''), array('mask_checksum', 'card', ''), array('mask_store', 'raw', 'canonical'), array('mask_store', '', 'canonical'),
	array('mask_strict', 'yes', '0'), array('mask_strict', 2, '0'), array('inputmode', 'bogus', 'auto'), array('inputmode', 'NUMERIC', 'auto'), array('inputmode', '', 'auto'),
	array('mask_case', 'UPPER', ''), array('mask_case', 'title', ''), array('mask_case', array('upper'), ''),
);
$rows = array();
foreach ($bad as $i => $b) {
	$rows[] = fld('b' . $i, array($b[0] => $b[1]));
}
$out = save_and_read($fn, builder_payload(form_with($rows)));
foreach ($bad as $i => $b) {
	t('reset ' . $b[0] . ' ' . json_encode($b[1], JSON_UNESCAPED_UNICODE), $out[$i + 2][$b[0]], $b[2]);
}

// 3. Why a mask may not end with a backslash: the builder transport would corrupt the JSON.
$raw = form_with(array(fld('t1', array('mask_preset' => 'custom', 'mask_custom' => '99\\\\'))));
t('a trailing backslash breaks the builder payload (reason for the rule)', save_and_read($fn, builder_payload($raw)), null);
t('check_mask refuses a trailing backslash', \Emsfb\Masks::check_mask('99\\\\'), false);
$raw = form_with(array(fld('t2', array('mask_preset' => 'custom', 'mask_custom' => '99\\\\'))));
$direct = $fn->sanitize_obj_msg_efb($raw);
t('sanitizer resets a trailing-backslash mask sent outside the builder', $direct[2]['mask_custom'], '');

// 4. An old form without mask keys comes back with exactly the same keys and values.
$old = form_with(array(
	fld('o1', array('milen' => '3', 'mlen' => '20', 'placeholder' => 'Your name')),
	fld('o2', array('type' => 'tel', 'elementId' => 'tel', 'mlen' => '15')),
	array('id_' => 'o3', 'dataId' => 'o3-id', 'type' => 'email', 'name' => 'Email', 'step' => '1', 'amount' => '4', 'required' => '1'),
));
$out = save_and_read($fn, builder_payload($old));
$keysBefore = array_map(function ($r) { return array_keys($r); }, $old);
$keysAfter = array_map(function ($r) { return array_keys($r); }, $out);
t('old form: same keys on every row', $keysAfter, $keysBefore);
t('old form: no mask key appears', strpos(json_encode($out), 'mask_') === false && strpos(json_encode($out), 'inputmode') === false, true);
t('old form: field values unchanged', array($out[2]['milen'], $out[2]['mlen'], $out[2]['placeholder'], $out[3]['mlen']), array('3', '20', 'Your name', '15'));

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
