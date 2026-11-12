<?php
/**
 * PAY00 §10.2: every payment-field key (pf_*, mobile_pf_*) and payment-action key (pay_act_*)
 * survives a real save round trip exactly as the builder sends it (the F01 harness: JSON.stringify ->
 * transport -> wp_unslash -> sanitize_obj_msg_efb -> stored column -> reader), each through its own
 * case of sanitize_obj_msg_efb(); hostile values are reset; a form without the keys is unchanged.
 * The default case would have kept '"><script>' as text and wiped pf_i18n_b64 of its '=' padding.
 *
 * Run: php tests/test-pay-save-roundtrip.php
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

$b64 = rtrim(strtr(base64_encode(json_encode(array('fa_IR' => array('btn' => 'پرداخت %amount%', 'secure' => 'امن'), 'de_DE' => array('btn' => 'Jetzt %amount% zahlen')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
$good = array(
	'pf_btn_text' => 'Pay %amount% now', 'pf_btn_bg' => '#14532d', 'pf_btn_fg' => 'auto', 'pf_btn_bg_h' => '#0f3d21', 'pf_btn_fg_h' => '#ffffff',
	'pf_btn_radius' => '12', 'pf_btn_size' => 'lg', 'pf_btn_width' => 'auto', 'pf_btn_icon' => 'bi-heart', 'pf_btn_icon_pos' => 'end',
	'pf_order' => 'button,summary,secure', 'pf_hide' => 'logos,methods', 'pf_layout' => 'split', 'pf_methods_style' => 'grid', 'pf_methods' => 'ideal,card',
	'pf_summary' => 'total', 'pf_label_pos' => 'hidden', 'pf_card_bg' => '#fafafa', 'pf_card_border' => '#dddddd', 'pf_card_radius' => '20', 'pf_accent' => '#0d6efd',
	'pf_logo_size' => 'md', 'pf_digits' => 'locale', 'pf_i18n_b64' => $b64, 'pf_secure_text' => 'Secure', 'pf_t_summary' => 'Your order', 'pf_t_methods' => 'Pay with',
	'pf_t_pending' => 'Waiting', 'pf_t_success' => 'Thanks', 'pf_t_fail' => 'Sorry', 'pf_t_amount' => 'Gift', 'pf_fixed' => '2.50', 'pf_fixed_label' => 'Fee',
	'pf_amount' => 'donation', 'pf_presets' => '10,25,50', 'pf_other' => '1', 'pf_default' => '25', 'pf_min' => '5', 'pf_max' => '500',
	'mobile_pf_order' => 'secure,button', 'mobile_pf_hide' => 'summary', 'mobile_pf_btn_size' => 'sm', 'mobile_pf_btn_width' => 'full', 'mobile_pf_methods_style' => 'select',
);
$acts = array('pay_act_admin_mail' => 'both', 'pay_act_user_mail' => 'submit', 'pay_act_sms' => 'paid', 'pay_act_tlg' => 'submit', 'pay_act_gs' => 'both', 'pay_act_wh' => 'paid', 'pay_fail_notify' => 'admin');
$form = array(array_merge(array('type' => 'payment', 'getway' => 'efbmock', 'currency' => 'EUR'), $acts), array('id_' => '1', 'type' => 'step'), array_merge(array('id_' => 'pf1', 'type' => 'efbmock', 'name' => 'Pay'), $good));
$back = save_and_read($fn, builder_payload($form));
$lost = array();
foreach ($good as $k => $v) if (!isset($back[2][$k]) || $back[2][$k] !== $v) $lost[$k] = isset($back[2][$k]) ? $back[2][$k] : null;
t('all ' . count($good) . ' pf_* keys survive the save unchanged', $lost, array());
$lost = array();
foreach ($acts as $k => $v) if (!isset($back[0][$k]) || $back[0][$k] !== $v) $lost[$k] = isset($back[0][$k]) ? $back[0][$k] : null;
t('all pay_act_* keys survive on row 0', $lost, array());
$evil = array('pf_btn_text' => '><script>alert(1)</script>', 'pf_btn_bg' => 'red;background:url(x)', 'pf_btn_fg' => 'expression(1)', 'pf_btn_radius' => '999', 'pf_btn_icon' => 'bi-x onload=x',
	'pf_order' => 'button,<b>', 'pf_hide' => 'fields,button', 'pf_i18n_b64' => 'not base64!', 'pf_presets' => '5,abc,-3', 'pf_fixed' => '1e9', 'pf_amount' => 'free', 'pay_act_admin_mail' => 'now', 'pf_card_radius' => array('x'));
$form2 = array(array('type' => 'payment', 'pay_act_admin_mail' => 'now'), array('id_' => '1', 'type' => 'step'), array_merge(array('id_' => 'pf1', 'type' => 'efbmock'), $evil));
$b2 = save_and_read($fn, builder_payload($form2));
t('hostile values are reset (text without tags, colours, sizes, lists, base64, amounts)', array($b2[2]['pf_btn_text'], $b2[2]['pf_btn_bg'], $b2[2]['pf_btn_fg'], $b2[2]['pf_btn_radius'], $b2[2]['pf_btn_icon'], $b2[2]['pf_order'], $b2[2]['pf_hide'], $b2[2]['pf_i18n_b64'], $b2[2]['pf_presets'], $b2[2]['pf_fixed'], $b2[2]['pf_amount'], $b2[0]['pay_act_admin_mail'], $b2[2]['pf_card_radius']),
	array('script alert(1) /script', '', '', '40', '', 'button', '', '', '5', '', '', '', ''));
$old = array(array('type' => 'payment', 'getway' => 'stripe', 'currency' => 'usd'), array('id_' => '1', 'type' => 'step'), array('id_' => 's1', 'type' => 'stripe', 'name' => 'Card'));
t('a Stripe form without the new keys comes back unchanged', save_and_read($fn, builder_payload($old)), $old);

// Gateway options (pgw_*): each one through the gateway's field_schema(), never the default case.
foreach (array('class-Emsfb-payment-http.php', 'class-Emsfb-payment-money.php', 'class-Emsfb-payment-secrets.php', 'class-Emsfb-payment-gateway.php', 'class-Emsfb-payment-registry.php', 'payment-field-functions-efb.php') as $f) require_once $EFB . '/includes/payments/' . $f;
class Rt_Gateway extends \Emsfb\Payments\Gateway {
	public function slug() { return 'rtgw'; }
	public function addon_key() { return ''; }
	public function label() { return 'RT'; }
	public function settings_schema() { return array(); }
	public function test_connection(\Emsfb\Payments\Settings $s, $mode) { return \Emsfb\Payments\Result::ok(); }
	public function create(\Emsfb\Payments\Payment $p, \Emsfb\Payments\Settings $s, \Emsfb\Payments\Checkout_Context $c) { return new \Emsfb\Payments\Create_Result(); }
	public function fetch(\Emsfb\Payments\Payment $p, \Emsfb\Payments\Settings $s) { return \Emsfb\Payments\Remote_State::error('x'); }
	public function parse_webhook(\WP_REST_Request $r, \Emsfb\Payments\Settings $s) { return null; }
	public function field_schema() {
		return array(
			array('key' => 'pgw_map_email', 'type' => 'field', 'label' => 'E-mail', 'field_types' => array('email')),
			array('key' => 'pgw_map_name', 'type' => 'field', 'label' => 'Name'),
			array('key' => 'pgw_region', 'type' => 'select', 'label' => 'Region', 'options' => array('eu' => 'EU', 'ae' => 'UAE')),
			array('key' => 'pgw_note', 'type' => 'text', 'label' => 'Note', 'max' => 20),
			array('key' => 'pgw_profile', 'type' => 'code', 'label' => 'Profile'),
			array('key' => 'pgw_save', 'type' => 'checkbox', 'label' => 'Save'),
		);
	}
}
if (!class_exists('WP_REST_Request')) { class WP_REST_Request {} }
\Emsfb\Payments\Registry::instance()->register(new Rt_Gateway());
$opts = array('pgw_map_email' => 'em1', 'pgw_map_name' => 'nm_2', 'pgw_region' => 'ae', 'pgw_note' => 'Hello, you', 'pgw_profile' => 'pfl_9.x', 'pgw_save' => '1');
$f3 = array(array('type' => 'payment', 'getway' => 'rtgw', 'currency' => 'EUR'), array('id_' => '1', 'type' => 'step'), array('id_' => 'em1', 'type' => 'email'), array_merge(array('id_' => 'pf1', 'type' => 'rtgw', 'name' => 'Pay'), $opts));
$b3 = save_and_read($fn, builder_payload($f3));
$got = array(); foreach ($opts as $k => $v) $got[$k] = isset($b3[3][$k]) ? $b3[3][$k] : null;
t('gateway options (pgw_*) from the gateway\'s field_schema() survive the save', $got, array('pgw_map_email' => 'em1', 'pgw_map_name' => 'nm_2', 'pgw_region' => 'ae', 'pgw_note' => 'Hello, you', 'pgw_profile' => 'pfl_9.x', 'pgw_save' => '1'));
$f4 = array(array('type' => 'payment'), array('id_' => '1', 'type' => 'step'), array('id_' => 'pf1', 'type' => 'rtgw', 'pgw_map_email' => 'x><script>', 'pgw_region' => 'us', 'pgw_note' => '<b>bold</b> and a very long note indeed', 'pgw_profile' => 'a b', 'pgw_save' => 'yes', 'pgw_BAD' => 'x', 'pgw_map_phone' => 'ph1', 'pgw_free' => '<i>t</i>'));
$b4 = save_and_read($fn, builder_payload($f4));
t('hostile gateway options are reset by their schema type (field id, select, text max, code, checkbox)', array($b4[2]['pgw_map_email'], $b4[2]['pgw_region'], $b4[2]['pgw_note'], $b4[2]['pgw_profile'], $b4[2]['pgw_save']), array('', '', 'b bold /b and a very', '', ''));
t('a malformed pgw_ key is dropped; keys the schema does not know are kept, cleaned (id / plain text)', array(array_key_exists('pgw_BAD', $b4[2]), $b4[2]['pgw_map_phone'], $b4[2]['pgw_free']), array(false, 'ph1', 'i t /i'));
$f5 = array(array('type' => 'payment'), array('id_' => '1', 'type' => 'step'), array('id_' => 'pf1', 'type' => 'notregistered', 'pgw_map_email' => 'em1', 'pgw_region' => 'us<x>'));
$b5 = save_and_read($fn, builder_payload($f5));
t('without the add-on: gateway options are kept, never lost (ids as ids, the rest as plain text)', array($b5[2]['pgw_map_email'], $b5[2]['pgw_region']), array('em1', 'us x'));

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
