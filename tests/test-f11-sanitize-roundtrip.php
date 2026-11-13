<?php
/**
 * F11: every row-0 key survives a real save round trip exactly as the builder sends it
 * (builder JSON → transport → wp_unslash → sanitize_obj_msg_efb → stored column → reader), each
 * through its own case (Form_Gate::sanitize_key); hostile values are reset; custom_css keeps its
 * base64 (sanitize_text_field would have taken nothing from it, but the CSS inside is cleaned);
 * after the save, Form_Gate::normalize_form_settings() drops a lim_email_field that is not an
 * email field and keeps the previous custom_css for a user without unfiltered_html; a form
 * without the keys comes back unchanged; F11 keys on a field row are plain text.
 *
 * Run: php tests/test-f11-sanitize-roundtrip.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
$EFB = __DIR__ . '/..';

function sanitize_text_field($v) {
	if (is_array($v) || is_object($v)) return '';
	$s = strip_tags((string) $v);
	$s = preg_replace('/%[a-f0-9]{2}/i', '', $s);
	$s = preg_replace('/[\r\n\t ]+/', ' ', $s);
	return trim($s);
}
function sanitize_key($k) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $k)); }
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
function current_user_can($c) { return !empty($GLOBALS['can'][$c]); }
$GLOBALS['efb_test_roles'] = array('administrator' => 'Administrator', 'editor' => 'Editor', 'subscriber' => 'Subscriber');

require $EFB . '/includes/json-efb.php';
require $EFB . '/includes/functions.php';
require $EFB . '/includes/class-Emsfb-rate-limit.php';
require $EFB . '/includes/class-Emsfb-form-css.php';
require $EFB . '/includes/class-Emsfb-form-gate.php';

$pass = 0;
$fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++;
	echo "[FAIL] $label\n      expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();
function builder_payload($form) { return str_replace('\\"', '"', json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
function save_and_read($fn, $payload, $old = null) {
	$post = wp_unslash(wp_slash($payload));
	$valp = json_decode(emsfb_json_normalize_efb($post), true);
	if (!is_array($valp)) return null;
	$valp = $fn->sanitize_obj_msg_efb($valp);
	$valp = \Emsfb\Form_Gate::normalize_form_settings($valp, $old);
	$stored = str_replace('"', '\"', json_encode($valp, JSON_UNESCAPED_UNICODE));
	return json_decode(emsfb_json_normalize_efb($stored), true);
}

$css = "/* mine */\n.efb .btn{border-radius:0 !important;}\n:root{--efb-x:\"a>b\"}\n@media (max-width:576px){.efb h4{font-size:1rem}}";
$b64 = 'b64:' . base64_encode($css);
$good = array(
	'lim_total' => '120', 'lim_user' => '1', 'lim_ip' => '3', 'lim_cookie' => '2', 'lim_email' => '1', 'lim_period' => 'week', 'lim_email_field' => 'em1',
	'lim_show_left' => '1', 'lim_full_msg' => 'Sorry, we are full.', 'lim_user_msg' => 'Again after %s.', 'sched_open' => '2026-12-01T09:00', 'sched_close' => '2026-12-31T23:59',
	'sched_grace' => '5', 'sched_pending_msg' => 'Opens %s', 'sched_closed_msg' => 'Closed %s', 'allowed_roles' => 'editor,subscriber', 'draft_on' => '1', 'draft_days' => '30',
	'partial_on' => '1', 'partial_days' => '60', 'partial_consent' => 'notice', 'partial_need_contact' => '0',
);
$GLOBALS['can'] = array('unfiltered_html' => true);
$form = array(array_merge(array('type' => 'form', 'stateForm' => '1', 'custom_css' => $b64), $good), array('id_' => '1', 'type' => 'step'), array('id_' => 'em1', 'type' => 'email', 'name' => 'Email'));
$back = save_and_read($fn, builder_payload($form));
$lost = array();
foreach ($good as $k => $v) if (!isset($back[0][$k]) || $back[0][$k] !== $v) $lost[$k] = isset($back[0][$k]) ? $back[0][$k] : null;
t('all ' . count($good) . ' F11 keys survive the save unchanged', $lost, array());
t('custom_css keeps its base64 through the save (quotes, >, newlines inside)', \Emsfb\Form_Css::decode($back[0]['custom_css']), ".efb .btn{border-radius:0 !important;}\n:root{--efb-x:\"a>b\"}\n@media (max-width:576px){\n.efb h4{font-size:1rem}\n}");

$evil = array('lim_total' => '-5', 'lim_user' => '1e3', 'lim_period' => 'hour', 'lim_email_field' => '><svg onload=1>', 'lim_show_left' => 'yes',
	'lim_full_msg' => '<script>alert(1)</script>Full', 'sched_open' => '2026-13-40T25:61', 'sched_close' => 'tomorrow', 'sched_grace' => '600',
	'allowed_roles' => 'administrator,ghost,<b>', 'draft_days' => '0', 'partial_consent' => 'silent', 'custom_css' => 'b64:' . base64_encode('</style><script>alert(1)</script>{}body{background:url(javascript:alert(1))}'));
$b2 = save_and_read($fn, builder_payload(array(array_merge(array('type' => 'form'), $evil), array('id_' => '1', 'type' => 'step'))));
t('hostile values are reset', array($b2[0]['lim_total'], $b2[0]['lim_user'], $b2[0]['lim_period'], $b2[0]['lim_email_field'], $b2[0]['lim_show_left'], $b2[0]['lim_full_msg'], $b2[0]['sched_open'], $b2[0]['sched_close'], $b2[0]['sched_grace'], $b2[0]['allowed_roles'], $b2[0]['draft_days'], $b2[0]['partial_consent'], $b2[0]['custom_css']),
	array('', '', '', '', '', 'alert(1)Full', '', '', '', 'administrator', '', 'checkbox', ''));
$b3 = save_and_read($fn, builder_payload(array(array('type' => 'form', 'lim_email' => '1', 'lim_email_field' => 'tx1'), array('id_' => '1', 'type' => 'step'), array('id_' => 'tx1', 'type' => 'text'))));
t('lim_email_field that is not an email field is dropped after the save', $b3[0]['lim_email_field'], '');
t('an array where a scalar belongs becomes \'\' (as WordPress does)', save_and_read($fn, builder_payload(array(array('type' => 'form', 'lim_total' => array('3')), array('id_' => '1', 'type' => 'step'))))[0]['lim_total'], '');

// A user without unfiltered_html: the previous CSS stays, whatever was sent.
$GLOBALS['can'] = array();
$old = array(array('type' => 'form', 'custom_css' => 'b64:' . base64_encode('.efb{color:red}')));
$b4 = save_and_read($fn, builder_payload(array(array('type' => 'form', 'custom_css' => 'b64:' . base64_encode('.efb{color:blue}')), array('id_' => '1', 'type' => 'step'))), $old);
t('no unfiltered_html: the stored CSS is kept', \Emsfb\Form_Css::decode($b4[0]['custom_css']), '.efb{color:red}');
$b5 = save_and_read($fn, builder_payload(array(array('type' => 'form', 'custom_css' => 'b64:' . base64_encode('.efb{color:blue}')), array('id_' => '1', 'type' => 'step'))), array(array('type' => 'form')));
t('no unfiltered_html and no previous CSS: none is stored', array_key_exists('custom_css', $b5[0]), false);
$GLOBALS['can'] = array('unfiltered_html' => true);

$plain = array(array('type' => 'form', 'formName' => 'Contact', 'stateForm' => '0'), array('id_' => '1', 'type' => 'step'), array('id_' => 'n1', 'type' => 'text', 'name' => 'Name'));
t('a form without F11 keys comes back unchanged', save_and_read($fn, builder_payload($plain)), $plain);
$fieldrow = save_and_read($fn, builder_payload(array(array('type' => 'form'), array('id_' => '1', 'type' => 'step'), array('id_' => 'n1', 'type' => 'text', 'lim_total' => '<b>5</b>'))));
t('an F11 key on a field row is plain text (row 0 only has meaning)', $fieldrow[2]['lim_total'], '5');

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
