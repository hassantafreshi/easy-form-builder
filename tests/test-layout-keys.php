<?php
/**
 * F10: the save rules of the layout keys (Layout::sanitize_key() through sanitize_obj_msg_efb(), with a
 * WordPress-faithful sanitize_text_field stub: arrays become ''), the review page's form keys and the
 * capability registry entries.
 *
 *  A  review_before_submit / review_hide_empty are '1' / '0'; review_title is plain text of at most 120
 *     characters (tags and %XX gone); vis_view (the page's own mark) is never stored; an old form without the
 *     keys comes back unchanged; review_on() is false on a form with a Stripe / PayPal / PersiaPay field and on
 *     login / register forms; the registry has setting:review_before_submit (free) and capabilities.layout.
 *
 * Run: php tests/test-layout-keys.php
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
function wp_check_invalid_utf8($s) { return preg_match('//u', (string) $s) ? (string) $s : ''; }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function sanitize_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><strong><em><i><br><p><a>'); }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s, $d = null) { return $s; }
$GLOBALS['efb_filters'] = array();
function apply_filters($h, $v) { $a = func_get_args(); array_shift($a); return isset($GLOBALS['efb_filters'][$h]) ? call_user_func_array($GLOBALS['efb_filters'][$h], $a) : $v; }
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
$GLOBALS['efb_plan'] = 'pro';
function efb_plan_at_least_efb($need) { $r = array('free' => 0, 'free_plus' => 1, 'pro' => 2); return $r[$GLOBALS['efb_plan']] >= $r[$need]; }

require $EFB . '/includes/json-efb.php';
require $EFB . '/includes/functions.php';
require_once $EFB . '/includes/class-Emsfb-layout.php';
eval('namespace Emsfb; class Builder_Api { public static function plan_at_least($need, $package = null) { $r = array("free" => 0, "free_plus" => 1, "pro" => 2); $have = $package === null ? $GLOBALS["efb_plan"] : array(1 => "pro", 2 => "free", 3 => "free_plus")[(int) $package]; return $r[$have] >= (isset($r[$need]) ? $r[$need] : 2); } }');
use Emsfb\Layout;

$passed = 0; $failed = 0;
function t($label, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { $passed++; echo "[PASS] {$label}\n"; return; }
	$failed++; echo "[FAIL] {$label}\n      expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();
/** As the builder sends a form: JSON over the wire, unslashed, decoded, sanitized. */
$save = function (array $rows) use ($fn) {
	$wire = json_decode(wp_unslash(addslashes(json_encode($rows))), true);
	return $fn->sanitize_obj_msg_efb($wire);
};
$form = function (array $row0 = array(), array $fields = array()) {
	return array_merge(array(array_merge(array('type' => 'form', 'steps' => 1, 'formName' => 'T'), $row0), array('id_' => '1', 'type' => 'step', 'step' => '1', 'name' => 'S')), $fields);
};

/* ---- A: review keys ---- */
$s = $save($form(array('review_before_submit' => true, 'review_hide_empty' => false, 'review_title' => 'Check <b>it</b> %41 now')));
t('A review_before_submit true → "1"', $s[0]['review_before_submit'], '1');
t('A review_hide_empty false → "0"', $s[0]['review_hide_empty'], '0');
t('A review_title: tags and %XX octets removed (plain text)', $s[0]['review_title'], 'Check it now');
$s = $save($form(array('review_before_submit' => 'yes', 'review_title' => str_repeat('ب', 150), 'vis_view' => 'public')));
t('A an unknown flag value → "0"', $s[0]['review_before_submit'], '0');
t('A review_title cut at 120 characters (Persian kept)', mb_strlen($s[0]['review_title'], 'UTF-8'), 120);
t('A vis_view (the page\'s mark) is never stored', array_key_exists('vis_view', $s[0]), false);
$s = $save($form(array('review_before_submit' => array('1'), 'review_title' => array('x'))));
t('A an array value is not stored as a flag', array_key_exists('review_before_submit', $s[0]), false);
t('A an array title is stored empty', $s[0]['review_title'], '');
$old = $form(array('email' => 'o@x.test'), array(array('id_' => 'a1', 'type' => 'text', 'name' => 'Name', 'step' => '1', 'amount' => '2')));
t('A an old form without the keys comes back with no F10 key', array_values(array_intersect(array_keys($save($old)[0]), Layout::FORM_KEYS)), array());

/* review_on */
t('A review_on: flag on', Layout::review_on($form(array('review_before_submit' => '1'))), true);
t('A review_on: no flag (old form)', Layout::review_on($form()), false);
foreach (array('stripe', 'paypal', 'persiaPay') as $gw) {
	t("A review_on: never with a {$gw} field (paid inside the form)", Layout::review_on($form(array('review_before_submit' => '1', 'type' => 'payment'), array(array('id_' => 'p', 'type' => $gw, 'step' => '1')))), false);
}
t('A review_on: a payment-framework field keeps it (the server quotes the amount)', Layout::review_on($form(array('review_before_submit' => '1', 'type' => 'payment'), array(array('id_' => 'p', 'type' => 'efbmock', 'step' => '1')))), true);
t('A json_needs_runtime: the stored column (escaped quotes)', Layout::json_needs_runtime('[{\"type\":\"form\",\"review_before_submit\":\"1\"}]'), true);
t('A json_needs_runtime: "0" is not on', Layout::json_needs_runtime('[{"type":"form","review_before_submit":"0"}]'), false);
t('A json_needs_runtime: an old form', Layout::json_needs_runtime('[{"type":"form"}]'), false);

/* registry */
$caps = Layout::capabilities(array('plan' => array('package_type' => 2), 'features' => array()));
t('A registry: setting:review_before_submit is free and available on Free', array($caps['features']['setting:review_before_submit']['package'], $caps['features']['setting:review_before_submit']['available_now']), array('free', true));
t('A registry: capabilities.layout lists the form keys (spec F10)', array($caps['layout']['spec'], $caps['layout']['form_keys']), array('F10', Layout::FORM_KEYS));
$GLOBALS['efb_filters']['efb_layout_plan'] = function ($need, $part) { return $part === 'review' ? 'pro' : $need; };
$caps = Layout::capabilities(array('plan' => array('package_type' => 2), 'features' => array()));
t('A registry: the owner may change a part\'s plan (efb_layout_plan)', array($caps['features']['setting:review_before_submit']['package'], $caps['features']['setting:review_before_submit']['available_now']), array('pro', false));
unset($GLOBALS['efb_filters']['efb_layout_plan']);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
