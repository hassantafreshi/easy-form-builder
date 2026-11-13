<?php
/**
 * Not a test. The F02 tests' environment: the F11 SQLite $wpdb stand-in (f11-test-db.php) with the
 * counter table (Rate_Limit::ddl()) and the challenge table (Verification::ddl()) made from the real
 * DDL, the WordPress functions the engine calls, and small stand-ins for the classes around it
 * (Form_Runtime, Payments\Service::form_rows, Request_Health, Client_Ip's privacy mode, the REST
 * request / response). A stand-in mailer and SMS provider record what would be sent.
 *
 * Helpers: vt($name, $actual, $expected), vt_done(), vt_env() (fresh database + settings),
 * vt_form($rows) (stores a form, returns its id), vt_req($route_body) (a REST request),
 * vt_token($fid, $sid) (a runtime token the stand-in accepts), vt_provider() (registers the fake SMS
 * sender "fake", standing in for the SMS add-on's WP SMS bridge), vt_mobile() / vt_email() (field rows).
 */
define('ABSPATH', __DIR__ . '/');
error_reporting(E_ALL);

$GLOBALS['vt_passed'] = 0;
$GLOBALS['vt_failed'] = 0;
function vt($name, $actual, $expected) {
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $GLOBALS['vt_passed']++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$GLOBALS['vt_failed']++;
}
function vt_done() {
	echo "\nRESULTS: {$GLOBALS['vt_passed']} passed, {$GLOBALS['vt_failed']} failed\n";
	exit($GLOBALS['vt_failed'] ? 1 : 0);
}

/* A WP_Error with data (the payment stand-in keeps none). */
if (!class_exists('WP_Error')) {
	class WP_Error {
		public $c; public $m; public $d;
		public function __construct($c = '', $m = '', $d = array()) { $this->c = $c; $this->m = $m; $this->d = $d; }
		public function get_error_code() { return $this->c; }
		public function get_error_message() { return $this->m; }
		public function get_error_data() { return $this->d; }
	}
}
class WP_REST_Response {
	public $data; public $status;
	public function __construct($d = null, $s = 200) { $this->data = $d; $this->status = $s; }
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
}
class VT_Request {
	private $j;
	public function __construct($j) { $this->j = $j; }
	public function get_json_params() { return $this->j; }
}

require_once __DIR__ . '/f11-test-db.php';

if (!function_exists('wp_remote_post')) { function wp_remote_post($url, $args = array()) { $args['method'] = 'POST'; return wp_remote_request($url, $args); } }
if (!function_exists('nocache_headers')) { function nocache_headers() {} }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return !empty($GLOBALS['vt_logged_in']); } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 0; } }
if (!function_exists('get_bloginfo')) { function get_bloginfo($k = '') { return 'Test Site'; } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); } }
if (!function_exists('wp_rand')) { function wp_rand($a = 0, $b = 0) { return random_int($a, $b ?: PHP_INT_MAX); } }
if (!function_exists('register_rest_route')) { function register_rest_route($ns, $r, $a) { $GLOBALS['vt_routes'][$ns . '/' . $r] = $a; } }
if (!function_exists('get_setting_Emsfb')) { function get_setting_Emsfb($m = '') { return isset($GLOBALS['vt_settings']) ? $GLOBALS['vt_settings'] : (object) array('emailSupporter' => 'owner@example.test', 'smtp' => true); } }
if (!function_exists('emsfb_is_email_sending_enabled_efb')) { function emsfb_is_email_sending_enabled_efb($s) { return !isset($GLOBALS['vt_email_off']); } }
if (!function_exists('efb_plan_at_least_efb')) {
	function efb_plan_at_least_efb($need) {
		$rank = array('free' => 0, 'free_plus' => 1, 'pro' => 2);
		$have = isset($GLOBALS['vt_plan']) ? $GLOBALS['vt_plan'] : 'pro';
		return $rank[$have] >= (isset($rank[$need]) ? $rank[$need] : 2);
	}
}

/* Stand-ins of the classes around the engine. */
require_once __DIR__ . '/../includes/class-Emsfb-rate-limit.php';
require_once __DIR__ . '/../includes/class-Emsfb-drafts.php';
eval('namespace Emsfb; class Form_Runtime {
	public static function token($fid, $sid) { return "rt." . (int) $fid . "." . $sid; }
	public static function verify($t, $fid) { $p = explode(".", (string) $t); if (count($p) !== 3 || $p[0] !== "rt" || (int) $p[1] !== (int) $fid) return false; return array("sid" => $p[2], "fid" => (int) $fid, "owner" => "o", "t0" => 0); }
}
class Request_Health { public static function same_origin() { return empty($GLOBALS["vt_cross_origin"]); } }
class Client_Ip {
	public static function get() { return isset($GLOBALS["vt_ip"]) ? $GLOBALS["vt_ip"] : "203.0.113.7"; }
	public static function limit_key($fid) { $m = isset($GLOBALS["vt_ip_mode"]) ? $GLOBALS["vt_ip_mode"] : "store"; if ($m === "none") return ""; $ip = self::get(); return $m === "anon" ? preg_replace("/\\.\\d+$/", ".0", $ip) : $ip; }
}
class Email_Trace { public static function log($stage, $data) { $GLOBALS["vt_trace"][] = array($stage, $data); } }
');
eval('namespace Emsfb\Payments; class Service { public static function form_rows($fid) { return isset($GLOBALS["vt_forms"][(int) $fid]) ? $GLOBALS["vt_forms"][(int) $fid] : null; } }');
eval('class VT_Fn { public function efb_code_validate_check($sid, $fid) { return empty($GLOBALS["vt_dead_sid"]); } public function text_efb($k) { return array(); } public function is_efb_pro($n) { return true; }
	public function send_email_state_new($to, $sub, $cont, $pro, $state, $link, $st) { $GLOBALS["vt_mails"][] = compact("to", "sub", "cont", "state"); return isset($GLOBALS["vt_mail_result"]) ? $GLOBALS["vt_mail_result"] : true; } }');
if (!function_exists('get_efbFunction')) { function get_efbFunction() { static $f = null; return $f ?: ($f = new VT_Fn()); } }

require_once __DIR__ . '/../includes/verification/interface-provider.php';
require_once __DIR__ . '/../includes/class-Emsfb-verification.php';
use Emsfb\Verification;

/** The fake SMS sender: records every message (the code included, as a real phone would get it). */
class VT_Fake_Provider implements \Emsfb\Verification\Provider {
	public $ready = true;
	public function id() { return 'fake'; }
	public function label() { return 'Fake'; }
	public function channels() { return array('sms'); }
	public function ready() { return $this->ready; }
	public function send(array $msg) {
		$GLOBALS['vt_sms'][] = $msg;
		if (!empty($GLOBALS['vt_sms_fail'])) return array('ok' => false, 'error' => 'fake_500');
		return array('ok' => true, 'error' => '');
	}
}

function vt_env() {
	$db = $GLOBALS['wpdb'] = new EfbF11TestDb();
	$db->create_f11_tables();
	$db->exec_mysql_ddl(Verification::ddl($db->prefix, ''));
	\Emsfb\Rate_Limit::reset_ready();
	\Emsfb\Rate_Limit::$now = null;
	Verification::reset_cache();
	$GLOBALS['opts'] = array();
	$GLOBALS['efb_filters'] = array();
	$GLOBALS['efb_transients'] = array();
	foreach (array('vt_sms', 'vt_mails', 'efb_mails', 'vt_trace') as $k) $GLOBALS[$k] = array();
	foreach (array('vt_sms_fail', 'vt_email_off', 'vt_dead_sid', 'vt_cross_origin', 'vt_mail_result', 'vt_ip_mode') as $k) unset($GLOBALS[$k]);
	$GLOBALS['vt_ip'] = '203.0.113.7';
	$GLOBALS['vt_plan'] = 'pro';
	$GLOBALS['vt_forms'] = array();
	Verification::$now = 1800000000;
	Verification::$mailer = null;
	return $db;
}
function vt_mobile($id = 'mob1', $extra = array()) {
	return array_merge(array('id_' => $id, 'type' => 'mobile', 'name' => 'Mobile', 'required' => 0, 'otp_enabled' => '1', 'otp_required_when' => 'filled', 'step' => 1), $extra);
}
function vt_email($id = 'eml1', $mode = 'otp', $extra = array()) {
	return array_merge(array('id_' => $id, 'type' => 'email', 'name' => 'Email', 'required' => 0, 'email_confirm_mode' => $mode, 'step' => 1), $extra);
}
function vt_form(array $fields, $type = 'form') {
	static $n = 10;
	$n++;
	$GLOBALS['vt_forms'][$n] = array_merge(array(array('type' => $type, 'formName' => 'F', 'stateForm' => 0)), $fields);
	return $n;
}
function vt_provider($countries = array('ir', 'us')) {
	$p = new VT_Fake_Provider();
	add_filter('efb_verify_providers', function ($l) use ($p) { $l['fake'] = $p; return $l; });
	Verification::reset_cache();
	Verification::save_settings(array('sms' => array('provider' => 'fake', 'allowed_countries' => $countries)));
	return $p;
}
function vt_req(array $body) { return new VT_Request($body); }
function vt_token($fid, $sid = 'sid-a') { return \Emsfb\Form_Runtime::token($fid, $sid); }
/** POST verify/send; returns [status, data] (data = response array or error code + data). */
function vt_send($fid, $field, $dest, $sid = 'sid-a', $extra = array()) {
	return vt_answer(Verification::rest_send(vt_req(array_merge(array('form_id' => $fid, 'field_id' => $field, 'destination' => $dest, 'runtime_token' => vt_token($fid, $sid)), $extra))));
}
function vt_check($fid, $field, $cid, $code, $sid = 'sid-a') {
	return vt_answer(Verification::rest_check(vt_req(array('form_id' => $fid, 'field_id' => $field, 'challenge_id' => $cid, 'code' => $code, 'runtime_token' => vt_token($fid, $sid)))));
}
function vt_answer($r) {
	if ($r instanceof WP_Error) { $d = $r->get_error_data(); return array(isset($d['status']) ? $d['status'] : 0, array('code' => $r->get_error_code(), 'm' => $r->get_error_message(), 'data' => $d)); }
	return array($r->get_status(), $r->get_data());
}
/** The last code a destination received (fake SMS or recorded e-mail). */
function vt_last_code() {
	if (!empty($GLOBALS['vt_mails'])) {
		$m = end($GLOBALS['vt_mails']);
		if (preg_match('/letter-spacing:6px[^>]*>(\d+)</', $m['cont'], $x)) return $x[1];
	}
	$s = end($GLOBALS['vt_sms']);
	return $s ? $s['code'] : '';
}
