<?php
/**
 * F12b add-on providers, standalone (no WordPress, no network): WordPress stubs, an in-memory option store, the
 * core CAPTCHA layer and Payments\Secrets (the add-ons' secrets are encrypted with it), a stubbed wp_remote_post and
 * the add-on folder (core's vendor/<dir>, else ../vendores-efb/<dir>). Used by tests/addons/test-*-provider.php.
 */
namespace {
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
class WP_Error { public $c; public function __construct($c = '', $m = '') { $this->c = $c; } public function get_error_code() { return $this->c; } }
$GLOBALS['T'] = array('calls' => array(), 'reply' => null, 'filters' => array(), 'settings' => array(), 'options' => array(), 'transients' => array(), 'actions' => array(), 'routes' => array());
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_remote_post($url, $args) { $GLOBALS['T']['calls'][] = array('url' => $url, 'args' => $args); $r = $GLOBALS['T']['reply']; return is_callable($r) ? $r($url, $args) : $r; }
function wp_remote_retrieve_body($r) { return is_array($r) && isset($r['body']) ? $r['body'] : ''; }
function apply_filters($h, $v) { $a = func_get_args(); return isset($GLOBALS['T']['filters'][$h]) ? call_user_func_array($GLOBALS['T']['filters'][$h], array_slice($a, 1)) : $v; }
function add_filter($h, $cb) { $GLOBALS['T']['filters'][$h] = $cb; }
function do_action($h) { $a = func_get_args(); foreach ($GLOBALS['T']['actions'][$h] ?? array() as $cb) call_user_func_array($cb, array_slice($a, 1)); }
function add_action($h, $cb) { $GLOBALS['T']['actions'][$h][] = $cb; }
function home_url() { return 'https://www.site.test'; }
function get_setting_Emsfb($m = 'decoded') { return json_decode(json_encode($GLOBALS['T']['settings'])); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function get_locale() { return $GLOBALS['T']['locale'] ?? 'en_US'; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['T']['options']) ? $GLOBALS['T']['options'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['T']['options'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['T']['options'][$k]); return true; }
function set_transient($k, $v, $t) { $GLOBALS['T']['transients'][$k] = $v; return true; }
function get_transient($k) { return $GLOBALS['T']['transients'][$k] ?? false; }
function delete_transient($k) { unset($GLOBALS['T']['transients'][$k]); return true; }
function wp_salt($s = '') { return 'unit-test-salt-not-a-real-one'; }
function register_rest_route($ns, $route, $args) { $GLOBALS['T']['routes'][$ns . '/' . $route] = $args; }
function get_efbFunction() { return new Efb_Test_Fn(); }
/** Like core's set_setting_Emsfb(): the wipe guard keeps every top-level key the caller leaves out (F12b-L). */
class Efb_Test_Fn { public function set_setting_Emsfb($s, $e = '') { $GLOBALS['T']['writes'][] = array_keys((array) json_decode(json_encode($s), true)); $GLOBALS['T']['settings'] = array_merge((array) $GLOBALS['T']['settings'], (array) json_decode(json_encode($s), true)); return true; } }
}
namespace Emsfb {
/** In-memory counters (Rate_Limit::reserve / hit) like the real table's one-place semantics. */
class Rate_Limit {
	public static $rows = array();
	public static function ready() { return true; }
	public static function reserve($scope, $key, $slot, $end, $limit, $seed = 0) { $b = $scope . '|' . $key . '|' . $slot; $n = self::$rows[$b] ?? 0; if ($n >= $limit) return false; self::$rows[$b] = $n + 1; return true; }
	public static function hit($scope, $key, $window, $limit) { return self::reserve($scope, $key, (string) floor(time() / max(1, $window)), '', $limit); }
	public static function hash($p, $v) { return hash('sha256', $p . '|' . $v); }
}
}
namespace {
$EFB_ROOT = dirname(__DIR__, 2);
require_once $EFB_ROOT . '/includes/class-Emsfb-captcha.php';
require_once $EFB_ROOT . '/includes/class-Emsfb-spam.php';
require_once $EFB_ROOT . '/includes/payments/class-Emsfb-payment-secrets.php';

/** The add-on's folder (core's vendor/ in a dev checkout, else the add-ons repository next to core). */
function efb_addon_dir($dir) {
	$root = dirname(__DIR__, 2);
	return is_dir("$root/vendor/$dir") ? "$root/vendor/$dir" : dirname($root) . "/vendores-efb/$dir";
}

$efb_pass = 0; $efb_fail = 0;
function t($name, $actual, $expected) {
	global $efb_pass, $efb_fail;
	if ($actual === $expected) { $efb_pass++; echo "[PASS] {$name}\n"; }
	else { $efb_fail++; echo "[FAIL] {$name}\n  expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual:   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n"; }
}
function efb_done() { global $efb_pass, $efb_fail; echo "\nRESULTS: {$efb_pass} passed, {$efb_fail} failed\n"; exit($efb_fail ? 1 : 0); }
/** A siteverify answer (JSON) with this site's hostname and now. */
function efb_ok(array $x = array()) { return array('body' => json_encode(array_merge(array('success' => true, 'hostname' => 'site.test', 'challenge_ts' => gmdate('Y-m-d\TH:i:s\Z')), $x))); }
function efb_tok($s = '') { return str_pad('tok-' . $s . '-' . bin2hex(random_bytes(6)), 48, 'x'); }
}
