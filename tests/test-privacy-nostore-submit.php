<?php
/**
 * F13 sub-task C: "do not store entries" (prv_nostore).
 *
 * The real store gate (the code between the "// Privacy store gate:" markers of
 * _Public::store_entry_efb(), with begin/finish_operation_efb() and insert_message_db()) is lifted
 * from includes/class-Emsfb-public.php and run with a fixture (pattern of
 * tests/test-sms-failure-email-continuation.php), against the real Privacy and Upload_Guard
 * classes on an in-memory SQLite database:
 *  1. a "form" with prv_nostore: no row in emsfb_msg_, a tracking code, the request marked saved,
 *     the operation finished (so a replay answers the first result and sends nothing again);
 *  2. a replay of the same request: no second processing;
 *  3. a payment / survey / register / login form whose JSON says prv_nostore is still stored;
 *  4. an uploaded file survives the 24-hour orphan sweeper and is deleted after
 *     prv_nostore_file_days; "code and link only" e-mail becomes "content and link";
 *  5. the submit handler uses the gate for form and subscribe, and gives the e-mail no link.
 *
 * Run: php tests/test-privacy-nostore-submit.php
 */
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$GLOBALS['opts'] = array();
$GLOBALS['settings'] = new stdClass();
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
function get_setting_Emsfb($m = 'decoded') { return $GLOBALS['settings']; }
function apply_filters($h, $v) { return $v; }
function do_action() {} function add_action() {} function add_filter() {}
function get_current_user_id() { return 0; }
function wp_create_nonce($x) { return 'nonce-' . $x; }
function __($s) { return $s; }
function is_wp_error($x) { return $x instanceof WP_Error; }
function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', (string) $s); }
class WP_Error { public function get_error_code() { return 'e'; } public function get_error_message() { return 'm'; } public function get_error_data() { return array('status' => 409); } }
class WP_REST_Response { public $d; public function __construct($d) { $this->d = $d; } public function get_data() { return $this->d; } public function get_status() { return 200; } }
class EfbJsonStop extends Exception { public $payload; public function __construct($p) { parent::__construct('json'); $this->payload = $p; } }
function wp_send_json($d, $s = 200) { throw new EfbJsonStop($d); }
function wp_send_json_error($d, $s = 200) { throw new EfbJsonStop($d); }
require __DIR__ . '/privacy-test-db.php';
require __DIR__ . '/../includes/class-Emsfb-upload-guard.php';
require __DIR__ . '/../includes/class-Emsfb-privacy.php';
eval('namespace Emsfb; class Form_Runtime { public static $log = array(); public static $done = array();
	static function begin($k, $f, $d) { self::$log[] = "begin"; $id = $d["request_id"]; if (isset(self::$done[$id])) return new \WP_REST_Response(array("success" => true, "data" => self::$done[$id])); self::$done[$id] = null; return $id; }
	static function finish($key, $r) { self::$log[] = "finish"; self::$done[$key] = $r; }
	static function abort($k) { self::$log[] = "abort"; } }
class Request_Health { public static $saved = 0; static function mark_saved() { self::$saved++; } static function record() {} }');
use Emsfb\Privacy;

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
t('the store gate markers are in the source', strpos($src, '// Privacy store gate:') !== false && strpos($src, '// End privacy store gate.') !== false, true);
$grab = function ($name) use ($src) {
	if (!preg_match('/\t(?:public|private) function ' . $name . '\(.*?\n\t\}\n/s', $src, $m)) throw new Exception('missing ' . $name);
	return $m[0];
};
eval('class StoreFixture { public $db; public $id; public $name = "F"; public $value = "[]"; public $ip = "203.0.113.1"; public $operation_key = ""; public $operation_data = array();
	private function generate_track_code_efb($s) { return "TRK" . mt_rand(10000, 99999); }
	private function claim_uploaded_files_efb($c) { \Emsfb\Upload_Guard::release_pending_uploads(array_keys(\Emsfb\Privacy::files_in_content($c, array()))); }
	' . $grab('begin_operation_efb') . $grab('finish_operation_efb') . $grab('gate_reserve_efb') . $grab('gate_after_insert_efb') . $grab('unique_claim_efb') . $grab('store_entry_efb') . $grab('insert_message_db') . $grab('efb_email_noti_type_efb') . ' }');

$tmp = sys_get_temp_dir() . '/efb-prv-ns-' . getmypid();
@mkdir($tmp . '/uploads/2026/11', 0777, true);
register_shutdown_function(function () use ($tmp) { exec('rm -rf ' . escapeshellarg($tmp)); });
efb_privacy_test_wp_stubs($tmp . '/uploads', 'http://example.test/wp-content/uploads');
$db = $GLOBALS['wpdb'] = new EfbPrivacyTestDb();

$form = function ($type, $nostore) { return array(array('type' => $type, 'prv_nostore' => $nostore), array('id_' => '1', 'type' => 'step')); };
$fx = function ($fid, $request_id) use ($db) { $f = new StoreFixture(); $f->db = $db; $f->id = $fid; $f->operation_data = array('type' => 'form', 'request_id' => $request_id, 'runtime_token' => 'x'); return $f; };

// 1. not stored
$file = $tmp . '/uploads/2026/11/efb-PLG-261112-NOSTORE1.pdf';
file_put_contents($file, 'x');
$GLOBALS['opts'][\Emsfb\Upload_Guard::PENDING_OPTION] = array('efb-PLG-261112-NOSTORE1.pdf' => time() - 2 * 86400);
$GLOBALS['settings'] = (object) array('prv_nostore_file_days' => '30');
$f = $fx(5, 'req-1');
$f->value = str_replace('"', '\\"', json_encode(array(array('id_' => 'e', 'type' => 'email', 'value' => 'a@example.test'), array('id_' => 'u', 'type' => 'file', 'value' => '@file@', 'url' => 'http://example.test/wp-content/uploads/2026/11/efb-PLG-261112-NOSTORE1.pdf'))));
$code = $f->store_entry_efb($form('form', '1'));
t('prv_nostore form: a tracking code is returned', (bool) preg_match('/^TRK\d+$/', $code), true);
t('prv_nostore form: no row in emsfb_msg_', $db->count('wp_emsfb_msg_'), 0);
t('prv_nostore form: the request is marked saved (Request Health)', \Emsfb\Request_Health::$saved, 1);
t('prv_nostore form: the operation is finished with the code (replay protection)', array(\Emsfb\Form_Runtime::$log, \Emsfb\Form_Runtime::$done['req-1']['track']), array(array('begin', 'finish'), $code));
$ledger = get_option(Privacy::NOSTORE_FILES_OPTION, array());
t('the uploaded file is in the not-stored ledger for 30 days', isset($ledger['efb-PLG-261112-NOSTORE1.pdf']) && abs($ledger['efb-PLG-261112-NOSTORE1.pdf'] - (time() + 30 * 86400)) < 5, true);
t('...and out of the 24-hour pending ledger', isset($GLOBALS['opts'][\Emsfb\Upload_Guard::PENDING_OPTION]['efb-PLG-261112-NOSTORE1.pdf']), false);
if (!function_exists('maybe_unserialize')) { function maybe_unserialize($v) { return $v; } }
$db->options = 'wp_emsfb_form';
\Emsfb\Upload_Guard::cleanup_orphan_uploads();
t('the orphan sweeper (24 h) does not delete it', file_exists($file), true);
Privacy::sweep_nostore_files(time() + 10 * 86400);
t('the daily job keeps it before prv_nostore_file_days', file_exists($file), true);
Privacy::sweep_nostore_files(time() + 31 * 86400);
t('...and deletes it after', array(file_exists($file), get_option(Privacy::NOSTORE_FILES_OPTION)), array(false, array()));

// 2. replay
$f2 = $fx(5, 'req-1');
try { $f2->store_entry_efb($form('form', '1')); $replay = null; } catch (EfbJsonStop $e) { $replay = $e->payload; }
t('replay of the same request answers the first result, nothing runs again', isset($replay['data']['track']) && $replay['data']['track'] === $code && \Emsfb\Request_Health::$saved === 1, true);

// subscribe
$code_s = $fx(6, 'req-2')->store_entry_efb($form('subscribe', '1'));
t('subscribe with prv_nostore: not stored either', array($db->count('wp_emsfb_msg_'), (bool) $code_s), array(0, true));

// 3. types that need the row, and forms without the key
foreach (array('payment', 'survey', 'register', 'login') as $i => $ty) {
	$fx(7 + $i, 'req-t' . $i)->store_entry_efb($form($ty, '1'));
	t("{$ty} form with a tampered prv_nostore is still stored", $db->count('wp_emsfb_msg_', 'form_id = ' . (7 + $i)), 1);
}
$fx(20, 'req-old')->store_entry_efb(array(array('type' => 'form'), array('id_' => '1', 'type' => 'step')));
t('an old form without the key is stored as before', $db->count('wp_emsfb_msg_', 'form_id = 20'), 1);
$fx(21, 'req-off')->store_entry_efb($form('form', '0'));
t('prv_nostore "0" is stored', $db->count('wp_emsfb_msg_', 'form_id = 21'), 1);

// 4. e-mail type
$fe = new StoreFixture();
t('"code and link only" becomes "content and link" when not stored', $fe->efb_email_noti_type_efb(array(array('type' => 'form', 'prv_nostore' => '1', 'email_noti_type' => 'cc'))), 'msg');
t('"code and link only" stays for a stored form', $fe->efb_email_noti_type_efb(array(array('type' => 'form', 'email_noti_type' => 'cc'))), 'cc');
t('"content only" stays when not stored', $fe->efb_email_noti_type_efb(array(array('type' => 'form', 'prv_nostore' => '1', 'email_noti_type' => 'just_msg'))), 'just_msg');

// map-based nostore()
Privacy::on_form_saved(30, $form('form', '1'));
t('Privacy::nostore(form_id) from the saved map', array(Privacy::nostore(30), Privacy::nostore(31)), array(true, false));

// 5. the submit handler
preg_match('/case "form":(.*?)case "payment":/s', $src, $mf);
$case_form = isset($mf[1]) ? $mf[1] : '';
t('form case stores through store_entry_efb()', strpos($case_form, '$this->store_entry_efb($form_fields_array, $style_trackingCode)') !== false && strpos($case_form, 'insert_message_db') === false, true);
t('form case: the e-mail and the notification rules get no link when not stored', substr_count($case_form, '$entry_link_efb') >= 3 && strpos($case_form, "send_email_Emsfb_( \$email_recipients,\$track_code ,\$is_pro,\$state_of_email,\$entry_link_efb") !== false, true);
t('form case: integrations still run with the code', strpos($case_form, 'efb_intgrate_with_3rd_party_services_efb($track_code') !== false, true);
t('form case: the session still records "send" (dashboard counts)', strpos($case_form, "efb_code_validate_update(\$session_id, 'send', \$track_code)") !== false, true);
preg_match('/case "subscribe":\s*\$track_code=(.*?)case "survey":/s', $src, $ms);
t('subscribe case stores through store_entry_efb() and sends no link', isset($ms[1]) && strpos($ms[1], 'store_entry_efb(') !== false && strpos($ms[1], '$entry_link_efb,$status_email') !== false, true);
$reg = file_get_contents(__DIR__ . '/../includes/class-Emsfb-builder-api.php');
t('registry: setting:privacy_nostore (free, prv_nostore)', strpos($reg, "'setting:privacy_nostore'        => array('free', null, array('prv_nostore'), 'prvNoStore')") !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
