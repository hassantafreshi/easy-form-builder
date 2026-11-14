<?php
/**
 * F05-A/B: every choice key (§5.1, §5.2) survives a real save round trip, exactly as the builder sends it
 * (JSON.stringify -> transport -> wp_unslash -> sanitize_obj_msg_efb -> stored column -> readers):
 * a bulk list of 500 choices with stored values in three scripts, flags, numbers, enums, the dynamic
 * source keys; a stored value used twice in one field is kept on the first choice only; the server's own
 * marks (dyn, inv_mode) never come from a save; the default of a radio follows a renamed choice id
 * (spec §19, fixed); old forms come back unchanged.
 *
 * Run: php tests/test-choices-save-roundtrip.php
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

/* Parent keys: flags, numbers, enums, plain text. */
roundtrip_keys($fn, array(
	array('op_random', '1', '1', array('type' => 'radio')), array('op_random', true, '1'), array('op_random', 'yes', ''), array('op_random', '0', ''),
	array('op_other', '1', '1', array('type' => 'checkbox')), array('op_other_label', 'Something <b>else</b>', 'Something else'), array('op_other_label', str_repeat('د', 90), str_repeat('د', 80)),
	array('op_other_max', '۳۰', '30'), array('op_other_max', '5000', '1000'), array('op_other_max', '0', '1'), array('op_other_max', 'x', ''),
	array('op_select_all', '1', '1'), array('minSelect', '2', '2', array('type' => 'checkbox')), array('maxSelect', '٣', '3'), array('maxSelect', '-1', ''), array('maxSelect', array(2), ''),
	array('inv_on', '1', '1', array('type' => 'radio')), array('inv_show', 'hide', 'hide'), array('inv_show', 'LABEL', 'label'), array('inv_show', 'explode', ''), array('inv_left', '1', '1'), array('inv_release', '1', '1'),
	array('dyn_src', 'posts', 'posts', array('type' => 'select')), array('dyn_src', 'entries', ''), array('dyn_post_type', 'Product<script>', 'productscript'), array('dyn_tax', 'product_cat', 'product_cat'),
	array('dyn_term', '12, News ,x<y', '12,news,xy'), array('dyn_role', 'author,EDITOR', 'author,editor'), array('dyn_orderby', 'date', 'date'), array('dyn_orderby', 'rand', ''), array('dyn_order', 'desc', 'DESC'),
	array('dyn_limit', '900', '500'), array('dyn_limit', '0', '1'), array('dyn_csv', '42', '42'), array('dyn_csv', 'http://evil.test/x.csv', ''),
	array('rank_top', '3', '3', array('type' => 'ranking')), array('rank_touch', '0', '0'), array('rank_touch', '1', '1'), array('rank_touch', false, '0'),
	array('dyn', '1', '(missing)'), array('inv_mode', 'booking', '(missing)'),
));

/* A bulk list: 500 choices, stored values in Latin, Persian and Arabic, one repeated (kept on the first only). */
$rows = array(fld('sel', array('type' => 'select', 'elementId' => 'select', 'op_random' => '1')));
for ($i = 0; $i < 500; $i++) {
	$v = $i % 3 === 0 ? 'IR-' . $i : ($i % 3 === 1 ? 'م' . $i : 'و' . $i);
	$rows[] = opt('o' . $i, 'sel', 'Choice ' . $i . ' & x', array('opt_value' => $v));
}
$rows[] = opt('dup', 'sel', 'Again', array('opt_value' => 'IR-0', 'op_lock' => '1', 'inv_cap' => '۵'));
$rows[] = opt('bad', 'sel', 'Bad', array('opt_value' => '<b>v</b>>', 'inv_cap' => '-3'));
$t0 = microtime(true);
$out = save_and_read($fn, builder_payload(form_with($rows)));
$ms = (microtime(true) - $t0) * 1000;
t('500 choices come back after the save', is_array($out) ? count(array_filter($out, function ($r) { return ($r['type'] ?? '') === 'option'; })) : 0, 502);
t('stored values in three scripts kept', array($out[3]['opt_value'], $out[4]['opt_value'], $out[5]['opt_value']), array('IR-0', 'م1', 'و2'));
t('a stored value already used in the field is emptied on the later choice', $out[503]['opt_value'], '');
t('op_lock and inv_cap (Persian digits) kept', array($out[503]['op_lock'], $out[503]['inv_cap']), array('1', '5'));
t('a stored value with markup and < > is cleaned; a negative capacity removed', array($out[504]['opt_value'], $out[504]['inv_cap']), array('v', ''));
t('the label keeps &', strpos($out[3]['value'], '&') !== false, true);
t('the round trip of 500 choices takes under 2 s', $ms < 2000, true);

/* Two fields may use the same stored value. */
$out = save_and_read($fn, builder_payload(form_with(array(fld('a', array('type' => 'radio')), opt('a1', 'a', 'X', array('opt_value' => 'v')), fld('b', array('type' => 'radio')), opt('b1', 'b', 'Y', array('opt_value' => 'v'))))));
t('the same stored value in two different fields is kept in both', array($out[3]['opt_value'], $out[5]['opt_value']), array('v', 'v'));

/* Spec §19: renaming a choice id keeps the radio's default on it (the default wrote to a local copy before). */
$out = save_and_read($fn, builder_payload(form_with(array(fld('r', array('type' => 'radio', 'value' => 'r1')), opt('r1', 'r', 'One', array('id' => 'renamed'))))));
t('a renamed choice: the radio default follows the new id', array($out[2]['value'], $out[3]['id_'], $out[3]['id_old']), array('renamed', 'renamed', 'r1'));

/* An old form without the keys comes back with the same keys. */
$old = form_with(array(fld('m', array('type' => 'multiselect', 'maxSelect' => '2', 'minSelect' => '0')), opt('m1', 'm', 'A'), opt('m2', 'm', 'B'), fld('c', array('type' => 'checkbox')), opt('c1', 'c', 'Tehran|THR')));
$back = save_and_read($fn, builder_payload($old));
t('old form: the multiselect limits unchanged', array($back[2]['maxSelect'], $back[2]['minSelect']), array('2', '0'));
t('old form: no F05 key appears', array_values(array_intersect(array_keys($back[3] + $back[5] + $back[6]), array_merge(\Emsfb\Choices::OPTION_KEYS, \Emsfb\Choices::PARENT_KEYS))), array());
t('old form: a label with "|" is a label (no stored value made)', array($back[6]['value'], isset($back[6]['opt_value'])), array('Tehran|THR', false));

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
