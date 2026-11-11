<?php
/* Shared WordPress stubs for the AutoPilot admin-handler tests (test-ap-a5, test-ap-b4, test-ap-b5). Not a test. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!defined('EMSFB_PLUGIN_DIRECTORY')) define('EMSFB_PLUGIN_DIRECTORY', sys_get_temp_dir() . '/efb-ap-a5-' . getmypid() . '/'); // no vendor/ add-ons here
if (!defined('EMSFB_PLUGIN_VERSION')) define('EMSFB_PLUGIN_VERSION', '4.3.1-test');

class EfbA5Stop extends Exception { public $payload; public function __construct($p) { parent::__construct('json'); $this->payload = $p; } }
$GLOBALS['a5'] = array('settings' => (object) array('AdnSS' => 0, 'AdnTLG' => 0, 'emailSupporter' => ''), 'options' => array(), 'actions' => array(), 'text' => array('authFieldsMissing' => 'A %1$s form needs these fields: %2$s.'));

function add_action() {} function add_filter() {} function do_action($h) { $GLOBALS['a5']['actions'][] = $h; }
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
		foreach ((array) $keys as $k) $out[$k] = isset($GLOBALS['a5']['text'][$k]) ? $GLOBALS['a5']['text'][$k] : 'T:' . $k;
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
