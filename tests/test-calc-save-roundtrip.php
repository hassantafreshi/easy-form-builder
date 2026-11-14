<?php
/**
 * F04-D: every F04 key survives a real save round trip, as the builder sends it (JSON.stringify -> transport
 * -> wp_unslash -> sanitize_obj_msg_efb -> stored column -> readers): number keys, the calc field (the formula
 * byte for byte, show / empty, the amount switch), an option's calc value, the slider type; refused values
 * (an injected formula with < > " %, a step written as text, digits in another script normalized);
 * formula_err rebuilt by the server (a missing or forbidden field, a cycle, a broken formula) and never taken
 * from the save; F01's num_digits / num_prefix on a text field keep F01's rule; old forms come back unchanged.
 *
 * Run: php tests/test-calc-save-roundtrip.php
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
require $EFB . '/includes/class-Emsfb-field-rules.php';
require $EFB . '/includes/class-Emsfb-choices.php';
require $EFB . '/includes/class-Emsfb-calc.php';
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
		array('type' => 'form', 'steps' => '1', 'formName' => 'Rules', 'email' => ''),
		array('type' => 'step', 'id_' => '1', 'dataId' => '1-id', 'name' => 'Step 1', 'step' => '1'),
	), $rows);
}
function fld($id, $extra) {
	return array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => 'F ' . $id, 'step' => '1', 'amount' => '2', 'required' => '0', 'placeholder' => '', 'value' => ''), $extra);
}
/** One key on one field, through the round trip: [key, value as sent, expected stored value, extra row keys]. */
function roundtrip_keys($fn, array $cases) {
	$rows = array();
	foreach ($cases as $i => $c) $rows[] = fld('k' . $i, array_merge(isset($c[3]) ? $c[3] : array(), array($c[0] => $c[1])));
	$out = save_and_read($fn, builder_payload(form_with($rows)));
	t('the form decodes after the round trip (' . count($cases) . ' keys)', is_array($out), true);
	foreach ($cases as $i => $c) {
		$got = is_array($out) && isset($out[$i + 2]) && array_key_exists($c[0], $out[$i + 2]) ? $out[$i + 2][$c[0]] : '(missing)';
		t($c[0] . ' ' . json_encode($c[1], JSON_UNESCAPED_UNICODE) . ' → ' . json_encode($c[2], JSON_UNESCAPED_UNICODE), $got, $c[2]);
	}
}



function opt($id, $parent, $label, $extra = array()) {
	return array_merge(array('id_' => $id, 'dataId' => $id . '-id', 'parent' => $parent, 'type' => 'option', 'value' => $label, 'id_op' => $id, 'step' => '1', 'amount' => '2'), $extra);
}

/* Number keys on a number field. */
roundtrip_keys($fn, array(
	array('num_step', '0.5', '0.5', array('type' => 'number')),
	array('num_step', '۰٫۵', '0.5', array('type' => 'number')),
	array('num_step', '0', '(missing)', array('type' => 'number')),
	array('num_decimals', '2', '2', array('type' => 'number')),
	array('num_format', 'group', 'group', array('type' => 'number')),
	array('num_dec_sep', 'momayez', 'momayez', array('type' => 'number')),
	array('num_grp_sep', 'arabic', 'arabic', array('type' => 'number')),
	array('num_digits', 'native', 'native', array('type' => 'number')),
	array('num_prefix', '$', '$', array('type' => 'calc')),
	array('num_suffix', 'تومان', 'تومان', array('type' => 'calc')),
	array('num_currency', 'form', 'form', array('type' => 'number')),
	array('num_max_zero', true, '1', array('type' => 'number')),
	array('range_mode', 'dual', 'dual', array('type' => 'range')),
	array('calc_show', 'hidden', 'hidden', array('type' => 'calc', 'formula' => '1')),
	array('calc_empty', 'blank', 'blank', array('type' => 'calc', 'formula' => '1')),
	array('calc_as_price', '1', '1', array('type' => 'calc', 'formula' => '1')),
	array('milen', '-10', '-10', array('type' => 'number', 'num_step' => '1')),
	array('mlen', '9.5', '9.5', array('type' => 'number', 'num_step' => '1')),
	// F01's keys on a text field keep F01's rule (num_digits = decimal places there).
	array('num_digits', '2', '2', array('type' => 'text', 'mask_preset' => 'numeric')),
	array('num_digits', 'native', '0', array('type' => 'text', 'mask_preset' => 'numeric')),
	array('num_prefix', '$', '$', array('type' => 'text', 'mask_preset' => 'numeric')),
));
/* Refused values are not stored at all. */
$out = save_and_read($fn, builder_payload(form_with(array(
	fld('a', array('type' => 'number', 'num_step' => 'abc', 'num_decimals' => '9', 'num_format' => 'x', 'num_dec_sep' => ';', 'num_digits' => 'roman', 'num_currency' => 'eur')),
	fld('b', array('type' => 'calc', 'num_prefix' => '<img src=x onerror=1>x', 'formula' => '1')),
))));
t('refused number values are dropped', array_values(array_intersect(array_keys($out[2]), array('num_step', 'num_decimals', 'num_format', 'num_dec_sep', 'num_digits', 'num_currency'))), array());
t('a prefix with markup keeps plain text only', $out[3]['num_prefix'], 'x');

/* The calc field: the formula byte for byte. */
$formula = 'if(gt({field:q1},10),round(({p1}+50)*{q1}*0.9,2),({p1}+50)*{q1})';
$rows = array(fld('p1', array('type' => 'number')), fld('q1', array('type' => 'number')), fld('c1', array('type' => 'calc', 'formula' => $formula, 'formula_err' => '1')));
$out = save_and_read($fn, builder_payload(form_with($rows)));
t('a formula comes back byte for byte', $out[4]['formula'], $formula);
t('formula_err from the browser is not kept (the formula is fine)', array_key_exists('formula_err', $out[4]), false);
$again = save_and_read($fn, builder_payload($out));
t('a second save changes nothing', $again, $out);
t('Persian digits and the Arabic comma in a formula are normalized', save_and_read($fn, builder_payload(form_with(array(fld('a', array('type' => 'number')), fld('c', array('type' => 'calc', 'formula' => 'max({a}،۱۲)*۲'))))))[3]['formula'], 'max({a},12)*2');
foreach (array('{a} > 1' => 'a comparison with >', '{a}%2' => 'a % sign', '"x"' => 'a quote', '<script>alert(1)</script>' => 'a script tag', "1\n+2" => 'a newline (kept as a space)') as $bad => $why) {
	$o = save_and_read($fn, builder_payload(form_with(array(fld('a', array('type' => 'number')), fld('c', array('type' => 'calc', 'formula' => $bad))))));
	// null: the payload itself does not survive the transport (the builder refuses " < > before that).
	t('an injected formula is not stored as sent: ' . $why, $o !== null && $o[3]['formula'] === $bad, false);
}
$o = save_and_read($fn, builder_payload(form_with(array(fld('c', array('type' => 'calc', 'formula' => str_repeat('1+', 1001) . '1'))))));
t('a formula over 2000 characters is emptied', $o[2]['formula'], '');

/* formula_err rebuilt by the server. */
$o = save_and_read($fn, builder_payload(form_with(array(
	fld('pw', array('type' => 'password')),
	fld('c1', array('type' => 'calc', 'formula' => '{nope}+1')),
	fld('c2', array('type' => 'calc', 'formula' => '{pw}*2')),
	fld('c3', array('type' => 'calc', 'formula' => '{c4}+1')),
	fld('c4', array('type' => 'calc', 'formula' => '{c3}+1')),
	fld('c5', array('type' => 'calc', 'formula' => 'round(')),
	fld('c6', array('type' => 'calc', 'formula' => '{c1}')),
))));
t('formula_err: missing field, password, a cycle of two, a broken formula', array($o[3]['formula_err'] ?? '', $o[4]['formula_err'] ?? '', $o[5]['formula_err'] ?? '', $o[6]['formula_err'] ?? '', $o[7]['formula_err'] ?? ''), array('1', '1', '1', '1', '1'));
t('a calc that reads a calc with a problem is not marked itself', array_key_exists('formula_err', $o[8]), false);
t('form_errors codes', array_map(function ($e) { return $e['code']; }, \Emsfb\Calc::form_errors($o)), array('c1' => 'calcRef', 'c2' => 'calcForbidden', 'c3' => 'calcCycle', 'c4' => 'calcCycle', 'c5' => 'calcSyntax'));

/* An option's calc value. */
$o = save_and_read($fn, builder_payload(form_with(array(fld('r', array('type' => 'radio')), opt('o1', 'r', 'Small', array('calc' => '-12.5')), opt('o2', 'r', 'Large', array('calc' => '۳۰')), opt('o3', 'r', 'X', array('calc' => '1e5'))))));
t('option calc: -12.5 kept, ۳۰ → 30, 1e5 dropped', array($o[3]['calc'], $o[4]['calc'], array_key_exists('calc', $o[5])), array('-12.5', '30', false));
$o = save_and_read($fn, builder_payload(form_with(array(fld('t', array('type' => 'text', 'calc' => 'abc'))))));
t('a "calc" key on a non-option row keeps the old rule (sanitize_text_field)', $o[2]['calc'], 'abc');

/* Old forms. */
$old = form_with(array(fld('n', array('type' => 'number', 'milen' => '1', 'mlen' => '10')), fld('r', array('type' => 'range', 'milen' => '0', 'mlen' => '100', 'value' => '40')), fld('p', array('type' => 'prcfld', 'milen' => '5'))));
$o = save_and_read($fn, builder_payload($old));
t('an old form comes back unchanged', $o, json_decode(json_encode($old), true));

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
