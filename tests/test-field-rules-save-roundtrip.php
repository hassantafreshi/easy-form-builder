<?php
/**
 * F03: every field-rule key (§5) survives a real save round trip, exactly as the builder sends it:
 *   JSON.stringify -> builder transport (replace \" -> ") -> wp_unslash(POST) -> emsfb_json_normalize_efb
 *   -> json_decode -> sanitize_obj_msg_efb -> json_encode -> str_replace('"','\"') (stored column)
 *   -> emsfb_json_normalize_efb -> json_decode (every reader).
 * Values the builder cannot produce are reset to the key's neutral value; old forms come back unchanged.
 *
 * Run: php tests/test-field-rules-save-roundtrip.php
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

/* A: the message of every rule (plain text ≤ 200, %s kept; no tags, no " < >, one line). */
$cases = array();
foreach (\Emsfb\Field_Rules::MSG_RULES as $rule) {
	$cases[] = array('msg_' . $rule, 'The %s field: try again (' . $rule . ')', 'The %s field: try again (' . $rule . ')');
}
$cases[] = array('msg_pattern', "Only <b>letters</b>\nplease", 'Only letters please');
$cases[] = array('msg_unique', str_repeat('ک', 230), str_repeat('ک', 200));
$cases[] = array('msg_domain', array('x'), '');
$cases[] = array('msg_strength', 'Use 8+ chars & a digit', 'Use 8+ chars & a digit');
roundtrip_keys($fn, $cases);

/* B: words and the counter. */
roundtrip_keys($fn, array(
	array('wmin', '2', '2'), array('wmax', '۱۲۰', '120'), array('wmax', 0, ''), array('wmax', '999999', '100000'), array('wmin', '-1', ''), array('wmax', array(3), ''),
	array('counter', 'chars', 'chars', array('type' => 'textarea')), array('counter', 'words', 'words'), array('counter', 'lines', 'none'), array('counter', '', 'none'),
	array('counter_style', 'used', 'used'), array('counter_style', 'remaining', 'remaining'), array('counter_style', 'left', 'remaining'),
	array('mlen', '500', '500', array('type' => 'textarea')),
));

/* C: patterns travel as base64url, so < " %41 and double spaces come back unchanged. */
$b64 = function ($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); };
roundtrip_keys($fn, array(
	array('pattern_b64', $b64('x<y "q" %41  [A-Z]{2}'), $b64('x<y "q" %41  [A-Z]{2}')),
	array('pattern_b64', $b64('ب[آ-ی]{2,10}'), $b64('ب[آ-ی]{2,10}')),
	array('pattern_b64', $b64('(a+)+'), ''), array('pattern_b64', 'a b', ''), array('pattern_b64', base64_encode('a+?b/'), ''),
	array('pattern_case', '1', '1'), array('pattern_case', 'yes', '0'), array('pattern_ascii', '0', '0'), array('pattern_ascii', '', '1'),
));

/* D: unique. */
roundtrip_keys($fn, array(
	array('unique', '1', '1', array('type' => 'email')), array('unique', true, '1'), array('unique', 'yes', '0'), array('unique', 0, '0'),
	array('unique_scope', 'form', 'form'), array('unique_scope', 'site', 'form'),
));

/* E: e-mail domains (punycode, the shapes of §4.5). */
roundtrip_keys($fn, array(
	array('email_domain_mode', 'allow', 'allow', array('type' => 'email')), array('email_domain_mode', 'deny', 'deny'), array('email_domain_mode', 'block', 'none'),
	array('email_domains', 'Example.com; *.EDU bücher.de bad%41.com', 'example.com,*.edu,xn--bcher-kva.de'), array('email_domains', 'name@example.com *@corp.io', 'name@example.com,*@corp.io'),
	array('email_domains', array('x.com'), ''),
));

/* F: read-only. */
roundtrip_keys($fn, array(array('readonly', '1', '1'), array('readonly', true, '1'), array('readonly', 'on', '0'), array('readonly', '0', '0')));

/* G: prefix / suffix (plain, ≤ 24) and the description's place. */
roundtrip_keys($fn, array(
	array('prefix', '+98', '+98'), array('suffix', 'تومان', 'تومان'), array('prefix', '<b>$</b>', '$'), array('suffix', str_repeat('x', 30), str_repeat('x', 24)), array('prefix', '% off', '% off'),
	array('message_place', 'tooltip', 'tooltip'), array('message_place', 'above', 'above'), array('message_place', 'focus', 'focus'), array('message_place', 'side', 'below'),
));

/* H: passwords. */
roundtrip_keys($fn, array(
	array('pw_confirm', '1', '1', array('type' => 'password')), array('pw_meter', true, '1'), array('pw_toggle', 'no', '0'),
	array('pw_strength_min', '3', '3'), array('pw_strength_min', '7', '4'), array('pw_strength_min', '-2', '0'), array('pw_strength_min', '', '0'),
	array('pw_confirm_label', 'Type it <i>again</i>', 'Type it again'), array('pw_confirm_label', str_repeat('ر', 120), str_repeat('ر', 100)),
));

/* An old form without the keys comes back with the same keys. */
$old = form_with(array(fld('o1', array('milen' => '2', 'mlen' => '30', 'customRequiredMsg' => 'Please fill %s')), fld('o2', array('type' => 'textarea', 'value' => 'a@n#b'))));
$back = save_and_read($fn, builder_payload($old));
t('old form: no F03 key appears', array_values(array_intersect(array_keys($back[2] + $back[3]), \Emsfb\Field_Rules::KEYS)), array());
t('old form: its own keys unchanged', array($back[2]['milen'], $back[2]['mlen'], $back[2]['customRequiredMsg'], $back[3]['value']), array('2', '30', 'Please fill %s', 'a@n#b'));

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
