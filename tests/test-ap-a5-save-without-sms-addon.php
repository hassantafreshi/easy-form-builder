<?php
/**
 * AutoPilot A5: a form with SMS (or Telegram) switched on is saved even when that add-on is not active.
 *
 * update_form_id_Emsfb() refused the save ("install the add-on") when smsnoti=1 and the SMS add-on
 * (AdnSS / vendor/smssended/smsefb.php) was missing, after already writing the form, so the builder
 * reported a failure and kept the draft. Now the form is saved with smsnoti=0 (row 0 and fields),
 * the answer is success with a warning, and add-on-only secrets do not land in the public JSON.
 * Same for Telegram, and for a new form (add_form_structure).
 *
 * Runs the real handlers from includes/admin/class-Emsfb-admin.php and class-Emsfb-create.php with
 * WordPress stubs and an in-memory $wpdb.
 *
 * Run: php tests/test-ap-a5-save-without-sms-addon.php
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!defined('EMSFB_PLUGIN_DIRECTORY')) define('EMSFB_PLUGIN_DIRECTORY', sys_get_temp_dir() . '/efb-ap-a5-' . getmypid() . '/'); // no vendor/ add-ons here
if (!defined('EMSFB_PLUGIN_VERSION')) define('EMSFB_PLUGIN_VERSION', '4.3.1-test');

class EfbA5Stop extends Exception { public $payload; public function __construct($p) { parent::__construct('json'); $this->payload = $p; } }
$GLOBALS['a5'] = array('settings' => (object) array('AdnSS' => 0, 'AdnTLG' => 0, 'emailSupporter' => ''), 'options' => array());

function add_action() {} function add_filter() {} function do_action() {}
function update_option() { return true; } function add_option() { return true; }
function apply_filters($h, $v) { return $v; }
function is_admin() { return false; }
function wp_get_current_user() { return (object) array('ID' => 1); }
function get_current_user_id() { return 1; }
function current_user_can() { return true; }
function wp_verify_nonce() { return true; }
function check_ajax_referer() { return true; }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function sanitize_text_field($v) { return is_scalar($v) ? trim(strip_tags((string) $v)) : ''; }
function sanitize_email($v) { return (string) $v; }
function wp_date($f) { return date($f); }
function get_option($k, $d = false) { return isset($GLOBALS['a5']['options'][$k]) ? $GLOBALS['a5']['options'][$k] : $d; }
function wp_cache_set() { return true; }
function get_setting_Emsfb($mode = 'decoded', $d = null) { return $GLOBALS['a5']['settings']; }
function wp_send_json_success($data, $code = 200) { throw new EfbA5Stop($data); }
class EfbA5Fn {
	public function text_efb($keys) {
		$out = array();
		foreach ((array) $keys as $k) $out[$k] = 'T:' . $k;
		return $out;
	}
	public function user_permission_efb_admin_dashboard() { return true; }
	public function sanitize_obj_msg_efb($v) { return $v; }
}
function get_efbFunction() { return new EfbA5Fn(); }
class EfbA5Db {
	public $prefix = 'wp_'; public $rows = array(); public $insert_id = 0;
	public function update($t, $data, $where) { $this->rows[$where['form_id']] = $data; return 1; }
	public function insert($t, $data) { $this->insert_id = 42; $this->rows[42] = $data; return 1; }
	public function prepare($q) { return $q; }
	public function get_var($q) { return 1; }
}

require_once __DIR__ . '/../includes/json-efb.php';
if (file_exists(__DIR__ . '/../includes/class-Emsfb-builder-api.php')) require_once __DIR__ . '/../includes/class-Emsfb-builder-api.php';
require_once __DIR__ . '/../includes/admin/class-Emsfb-admin.php';

$pass = 0; $fail = 0;
function a5($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail) : '') . "\n"; }
}
function a5_form($extra0 = array()) {
	return array(
		array_merge(array('type' => 'form', 'formName' => 'Contact', 'steps' => 1, 'email' => ''), $extra0),
		array('type' => 'step', 'id_' => '1', 'step' => 1, 'amount' => 1),
		array('id_' => 'mob1', 'type' => 'mobile', 'name' => 'Phone', 'step' => 1, 'amount' => 2, 'smsnoti' => 1),
		array('id_' => 'nm1', 'type' => 'text', 'name' => 'Changed label', 'step' => 1, 'amount' => 3),
	);
}
function a5_update($form) {
	$admin = (new ReflectionClass('\Emsfb\Admin'))->newInstanceWithoutConstructor();
	$db = new EfbA5Db();
	$p = new ReflectionProperty('\Emsfb\Admin', 'db'); $p->setAccessible(true); $p->setValue($admin, $db);
	$_POST = array('value' => json_encode($form), 'id' => '7', 'name' => 'Contact', 'nonce' => 'n');
	try { $admin->update_form_id_Emsfb(); } catch (EfbA5Stop $s) { return array($s->payload, $db); }
	return array(null, $db);
}
function a5_stored($db, $id) {
	if (!isset($db->rows[$id]['form_structer'])) return null;
	return json_decode(str_replace('\"', '"', $db->rows[$id]['form_structer']), true);
}

// 1. SMS on, add-on missing.
list($r, $db) = a5_update(a5_form(array('smsnoti' => 1, 'sms_msg_new_noti' => 'New', 'sms_admins_phone_no' => '+989121234567')));
a5('SMS on without the add-on: the save succeeds', is_array($r) && $r['success'] === true && $r['r'] === 'updated', $r);
$warn = is_array($r) && isset($r['warnings']) ? $r['warnings'] : array();
a5('the answer carries a translated sms_disabled_addon_missing warning', count($warn) === 1 && $warn[0]['code'] === 'sms_disabled_addon_missing' && $warn[0]['m'] === 'T:smsOffAddonMissing', $warn);
$st = a5_stored($db, 7);
a5('the other changes are stored', is_array($st) && $st[3]['name'] === 'Changed label');
a5('smsnoti is stored as 0 on the form and on the mobile field', is_array($st) && intval($st[0]['smsnoti']) === 0 && intval($st[2]['smsnoti']) === 0, $st);
a5('the admins\' phone numbers do not land in the public form JSON', is_array($st) && !isset($st[0]['sms_admins_phone_no']));

// 2. Telegram on, add-on missing.
list($r, $db) = a5_update(a5_form(array('telegramnoti' => 1, 'telegram_bot_token' => '123:SECRET', 'telegram_admin_chat_ids' => '55')));
$st = a5_stored($db, 7);
a5('Telegram on without the add-on: the save succeeds with a warning', is_array($r) && $r['success'] === true && isset($r['warnings'][0]['code']) && $r['warnings'][0]['code'] === 'telegram_disabled_addon_missing', $r);
a5('telegramnoti stored as 0, bot token and chat ids not stored', is_array($st) && intval($st[0]['telegramnoti']) === 0 && !isset($st[0]['telegram_bot_token']) && !isset($st[0]['telegram_admin_chat_ids']), $st);

// 3. Nothing switched on: unchanged answer, no warnings key.
list($r, $db) = a5_update(a5_form());
a5('a form without SMS/Telegram saves exactly as before', is_array($r) && $r['success'] === true && $r['r'] === 'updated' && !isset($r['warnings']), $r);

// 4. New form (Create::add_form_structure) with SMS on and no add-on.
if (!function_exists('list_files')) { function list_files() { return array(); } }
if (!function_exists('get_transient')) { function get_transient() { return 'no'; } }
require_once __DIR__ . '/../includes/admin/class-Emsfb-create.php';
$create = (new ReflectionClass('\Emsfb\Create'))->newInstanceWithoutConstructor();
$cdb = new EfbA5Db();
$p = new ReflectionProperty('\Emsfb\Create', 'db'); $p->setAccessible(true); $p->setValue($create, $cdb);
$_POST = array('value' => json_encode(a5_form(array('smsnoti' => 1))), 'name' => 'Contact', 'type' => 'form', 'nonce' => 'n');
$r = null;
try { $create->add_form_structure(); } catch (EfbA5Stop $s) { $r = $s->payload; }
$st = isset($cdb->rows[42]['form_structer']) ? json_decode(str_replace('\"', '"', $cdb->rows[42]['form_structer']), true) : null;
a5('new form with SMS on and no add-on: inserted, smsnoti stored as 0, warning returned',
	is_array($r) && $r['success'] === true && $r['r'] === 'insert' && is_array($st) && intval($st[0]['smsnoti']) === 0 && isset($r['warnings'][0]['code']) && $r['warnings'][0]['code'] === 'sms_disabled_addon_missing', array($r, $st[0] ?? null));

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
