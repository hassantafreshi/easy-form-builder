<?php
/**
 * QA1-010 / QA1-001 (FX-ADMIN): Settings › Verification refuses a number out of its range instead of clamping it.
 *  - Verification::invalid_number() names the first value that is not a whole number in its range ('sms.ttl',
 *    'limits.ip_hour_sms'), reads Persian and Arabic-Indic digits, and lets every value in range through;
 *  - wp_ajax_efb_verify_settings_Emsfb (op=save) answers success:false, code invalid_<key>, key and a message with
 *    the range, and stores nothing (the saved value stays); a valid save is stored as before;
 *  - the ranges are the ones clamp() keeps (the screen's data-efb-num, verify-settings-efb.js RANGE, says the same).
 *
 * Run: php tests/test-verify-settings-numbers.php
 */
require __DIR__ . '/verify-test-lib.php';
use Emsfb\Verification as V;

if (!function_exists('check_ajax_referer')) { function check_ajax_referer() { return true; } }
if (!function_exists('current_user_can')) { function current_user_can() { return true; } }
if (!function_exists('number_format_i18n')) { function number_format_i18n($n) { return number_format((float) $n); } }
if (!function_exists('wp_unslash')) { function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; } }
if (!function_exists('sanitize_key')) { function sanitize_key($k) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $k)); } }
class VT_Json_Sent extends Exception { public $data; public function __construct($d) { parent::__construct('sent'); $this->data = $d; } }
if (!function_exists('wp_send_json_success')) { function wp_send_json_success($d = null, $s = 200) { throw new VT_Json_Sent($d); } }

vt_env();
$ajax = function (array $settings) {
	$_POST = array('op' => 'save', 'nonce' => 'n', 'settings' => json_encode($settings));
	try { V::ajax_settings(); } catch (VT_Json_Sent $e) { return $e->data; }
	return null;
};

// invalid_number()
vt('a value in range passes', V::invalid_number(array('sms' => array('ttl' => '420'), 'limits' => array('ip_hour_sms' => 7))), null);
vt('Persian and Arabic-Indic digits are read', V::invalid_number(array('sms' => array('ttl' => '۴۲۰'), 'email' => array('code_length' => '٧'))), null);
vt('a negative number is refused (was clamped to 3)', V::invalid_number(array('limits' => array('ip_hour_sms' => '-3'))), array('limits.ip_hour_sms', 1, 10000));
vt('letters are refused (code length "abc" became 6)', V::invalid_number(array('sms' => array('code_length' => 'abc'))), array('sms.code_length', 6, 8));
vt('too large is refused (99 kept the old value silently)', V::invalid_number(array('email' => array('code_length' => '99'))), array('email.code_length', 6, 8));
vt('too small is refused', V::invalid_number(array('sms' => array('ttl' => 60))), array('sms.ttl', 120, 600));
vt('an empty box is refused', V::invalid_number(array('email' => array('daily_budget' => ''))), array('email.daily_budget', 1, 100000));
vt('a decimal is refused', V::invalid_number(array('sms' => array('max_sends_dest_hour' => '2.5'))), array('sms.max_sends_dest_hour', 1, 20));
vt('keys not sent are not checked', V::invalid_number(array('sms' => array('message' => 'x'))), null);

// The AJAX save.
V::save_settings(array('sms' => array('ttl' => 300)));
$r = $ajax(array('sms' => array('ttl' => '-4', 'code_length' => '6')));
vt('the save answers success:false with invalid_sms_ttl and the key', array($r['success'], $r['code'], $r['key']), array(false, 'invalid_sms_ttl', 'sms.ttl'));
vt('…and says the range in words', $r['m'], 'Enter a whole number from 120 to 600.');
V::reset_cache();
vt('…and stores nothing (the saved 300 stays)', V::settings()['sms']['ttl'], 300);
$r = $ajax(array('limits' => array('fail_ip_hour' => 'abc')));
vt('a limit answers invalid_limits_fail_ip_hour', array($r['success'], $r['code']), array(false, 'invalid_limits_fail_ip_hour'));
$r = $ajax(array('sms' => array('ttl' => '420'), 'email' => array('blocked_domains' => 'mailinator.com')));
V::reset_cache();
vt('a valid save is stored', array($r['success'], V::settings()['sms']['ttl'], V::settings()['email']['blocked_domains']), array(true, 420, array('mailinator.com')));

// The screen says the same ranges.
$js = file_get_contents(__DIR__ . '/../includes/admin/assets/js/verify-settings-efb.js');
vt('the screen\'s ranges match the server\'s', (bool) preg_match("/code_length: '6,8', ttl: '120,600', daily_budget: '1,100000', dest_hour: '1,20', dest_day: '1,50', limit: '1,10000'/", $js), true);
// QA1-001: one Save; QA1-011: no label made from a key.
vt('the tab has no Save of its own (the page Save stores it: QA1-001)', strpos($js, 'vrfs-SaveBtn'), false);
vt('no limit label is the key with spaces (QA1-011)', strpos($js, "k.replace(/_/g, ' ')"), false);
foreach (array_keys(V::defaults()['limits']) as $k) {
	vt("the limit $k has its own phrase", (bool) preg_match('/\b' . $k . ": \\['vrfLim\\w+', '[A-Z][^']+'\\]/", $js), true);
}
$list = file_get_contents(__DIR__ . '/../includes/admin/assets/js/list_form-efb.js');
vt('the page Save stores the Verification tab first (efbVerifySettingsSave)', strpos($list, 'efbVerifySettingsSave().then(') !== false, true);

vt_done();
