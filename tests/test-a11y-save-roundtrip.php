<?php
/**
 * F16-F: the F16 keys through a real save round trip (the same path as tests/test-masks-save-roundtrip.php):
 * val_timing / err_summary / warn_unsaved on row 0 and autocomplete on a field are kept as the allow-lists say;
 * a hostile or unknown autocomplete ("evil onfocus") becomes "" (dropped, automatic); an F16 key on the wrong row is
 * dropped; a form without the keys comes back without them (old forms keep their behaviour).
 *
 * Run: php tests/test-a11y-save-roundtrip.php
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


$rows = array(
	fld('a1', array('elementId' => 'firstName', 'autocomplete' => 'given-name')),
	fld('a2', array('type' => 'date', 'elementId' => 'date', 'autocomplete' => 'BDAY')),
	fld('a3', array('autocomplete' => 'section-ship1 shipping street-address')),
	fld('a4', array('autocomplete' => 'evil onfocus')),
	fld('a5', array('autocomplete' => 'email onfocus=alert(1) <x>')),
	fld('a6', array('autocomplete' => 'off')),
	fld('a7', array('autocomplete' => '')),
	fld('a8', array('autocomplete' => array('x'))),
	fld('a9', array('val_timing' => 'blur', 'err_summary' => '1')),
);
$form = form_with($rows);
$form[0] = array_merge($form[0], array('val_timing' => 'blur', 'err_summary' => true, 'warn_unsaved' => '0', 'autocomplete' => 'email'));
$out = save_and_read($fn, builder_payload($form));
t('the form decodes after the round trip', is_array($out), true);
t('row 0: val_timing "blur" kept', $out[0]['val_timing'] ?? null, 'blur');
t('row 0: err_summary true -> "1"', $out[0]['err_summary'] ?? null, '1');
t('row 0: warn_unsaved "0" kept (an explicit no)', $out[0]['warn_unsaved'] ?? null, '0');
t('row 0: an autocomplete on row 0 means nothing and is dropped', array_key_exists('autocomplete', $out[0]), false);
t('field: given-name kept', $out[2]['autocomplete'] ?? null, 'given-name');
t('field: BDAY -> bday (lower case)', $out[3]['autocomplete'] ?? null, 'bday');
t('field: section-ship1 shipping street-address kept', $out[4]['autocomplete'] ?? null, 'section-ship1 shipping street-address');
t('field: "evil onfocus" -> dropped (automatic)', array_key_exists('autocomplete', $out[5]), false);
t('field: a value with markup and a handler -> dropped', array_key_exists('autocomplete', $out[6]), false);
t('field: "off" kept', $out[7]['autocomplete'] ?? null, 'off');
t('field: "" -> dropped (automatic)', array_key_exists('autocomplete', $out[8]), false);
t('field: an array -> dropped', array_key_exists('autocomplete', $out[9]), false);
t('field: form keys on a field row are dropped', array_key_exists('val_timing', $out[10]) || array_key_exists('err_summary', $out[10]), false);

$form = form_with(array(fld('b1', array()), fld('b2', array('elementId' => 'email', 'type' => 'email'))));
$form[0]['val_timing'] = 'sometimes';
$out = save_and_read($fn, builder_payload($form));
t('val_timing outside the list ("sometimes") is dropped: the form keeps the change timing', array_key_exists('val_timing', $out[0]), false);
$old = form_with(array(fld('c1', array())));
$out = save_and_read($fn, builder_payload($old));
t('a form without the keys comes back without them', array_key_exists('val_timing', $out[0]) || array_key_exists('err_summary', $out[0]) || array_key_exists('warn_unsaved', $out[0]) || array_key_exists('autocomplete', $out[2]), false);
t('efb_form_defaults_efb(): one step', (require_once $EFB . '/includes/inline-validation-functions-efb.php') !== false && efb_form_defaults_efb(1) === array('val_timing' => 'blur', 'err_summary' => '1', 'warn_unsaved' => '0'), true);
t('efb_form_defaults_efb(): several steps warn before leaving (open question 1)', efb_form_defaults_efb(3)['warn_unsaved'], '1');

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
