<?php
/**
 * F13 sub-task B: IP storage mode, per-form privacy keys, global settings and the
 * visitor's address (BUGS-FOUND: spoofable IP, raw IP in logs).
 *
 *  1. store_ip(): mode (store / anon / none) x form override (map entry) x global setting.
 *  2. anonymize_ip(): the fallback matches WordPress' own wp_privacy_anonymize_ip()
 *     (taken from the WordPress copy when available) on IPv4, IPv6, mapped and invalid input.
 *  3. client_ip(): forwarding headers count only from a trusted proxy (loopback/private,
 *     Cloudflare, or the efb_trusted_proxies filter); a direct visitor cannot choose the address.
 *  4. normalize_form_settings() and the emsfb_prv_forms map (save, defaults, delete).
 *  5. sanitize_setting() allow-lists; Persian / Arabic digits.
 *  6. set_settings_Emsfb(): only manage_options may change prv_*; keys a screen did not
 *     send are kept.
 *  7. efb_privacy_purge_ip_Emsfb: 403 without manage_options; anon / none on the three tables.
 *  8. The trace never writes a full IP; the submit answer has no "ip" key; every stored
 *     IP goes through Privacy::store_ip(); the session row drops os/browser on "none".
 *
 * Run: php tests/test-privacy-ip-mode.php   [WP copy for 2: EFB_WP_DIR=<wordpress> or the
 * scratchpad copy; the parity block is skipped when none is found]
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}

// ------------------------------------------------------------------ WordPress stubs
class EfbStop extends Exception { public $payload; public function __construct($p) { parent::__construct('json'); $this->payload = $p; } }
$GLOBALS['opts'] = array();
$GLOBALS['settings'] = new stdClass();
$GLOBALS['caps'] = array('manage_options' => true, 'Emsfb' => true);
$GLOBALS['filters'] = array();
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v, $autoload = null) { $GLOBALS['opts'][$k] = $v; $GLOBALS['autoload'][$k] = $autoload; return true; }
function get_setting_Emsfb($mode = 'decoded') { return $GLOBALS['settings']; }
function add_action() {} function do_action() {}
function add_filter($h, $cb) { $GLOBALS['filters'][$h][] = $cb; }
function apply_filters($h, $v) { if (isset($GLOBALS['test_filters'][$h])) return call_user_func($GLOBALS['test_filters'][$h], $v); return $v; }
function current_user_can($c) { return !empty($GLOBALS['caps'][$c]); }
function check_ajax_referer() { return true; }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function sanitize_text_field($v) { return is_scalar($v) ? trim(strip_tags((string) $v)) : ''; }
function sanitize_email($v) { return (string) $v; }
function wp_send_json_success($d, $c = 200) { throw new EfbStop($d); }
function is_admin() { return !empty($GLOBALS['is_admin']); }
function wp_get_current_user() { return (object) array('ID' => 1, 'user_email' => 'a@example.test'); }
function get_current_user_id() { return 1; }

require __DIR__ . '/../includes/class-Emsfb-privacy.php';
use Emsfb\Privacy;

// ------------------------------------------------------------------ 1. store_ip matrix
$ip4 = '203.0.113.77';
$ip6 = '2001:db8:85a3:8d3:1319:8a2e:370:7348';
t('no setting at all: the address is stored as before', Privacy::store_ip($ip4, 5), $ip4);
foreach (array('store' => $ip4, 'anon' => '203.0.113.0', 'none' => '') as $mode => $want) {
	$GLOBALS['settings'] = (object) array('prv_ip_mode' => $mode);
	t("global {$mode}, form without override", Privacy::store_ip($ip4, 5), $want);
}
$GLOBALS['settings'] = (object) array('prv_ip_mode' => 'anon');
t('global anon: IPv6 keeps only its /48 network', Privacy::store_ip($ip6, 5), '2001:db8:85a3::');
$GLOBALS['settings'] = (object) array('prv_ip_mode' => 'bogus');
t('unknown global value falls back to store', Privacy::store_ip($ip4, 5), $ip4);
$GLOBALS['settings'] = (object) array('prv_ip_mode' => 'store');
$GLOBALS['opts']['emsfb_prv_forms'] = array(5 => array('days' => '', 'ip' => 'none', 'nostore' => 0, 'id_field' => ''), 6 => array('days' => '', 'ip' => 'anon', 'nostore' => 0, 'id_field' => ''));
t('form override none beats global store', Privacy::store_ip($ip4, 5), '');
t('form override anon beats global store', Privacy::store_ip($ip4, 6), '203.0.113.0');
t('other form follows the global setting', Privacy::store_ip($ip4, 7), $ip4);
$GLOBALS['settings'] = (object) array('prv_ip_mode' => 'none');
t('form override anon beats global none', Privacy::store_ip($ip4, 6), '203.0.113.0');
t('store_agent false on none', Privacy::store_agent(5), false);
t('store_agent true on anon', Privacy::store_agent(6), true);
t('store_ip is idempotent (anon of anon)', Privacy::store_ip(Privacy::store_ip($ip4, 6), 6), '203.0.113.0');
t('log_ip never returns the full address (store mode)', Privacy::log_ip($ip4, 9, (object) array('prv_ip_mode' => 'store')), '203.0.113.0');
t('log_ip empty on none', Privacy::log_ip($ip4, 5), '');
$GLOBALS['opts'] = array();
$GLOBALS['settings'] = new stdClass();

// ------------------------------------------------------------------ 2. anonymize_ip parity with WordPress
$vectors = array('1.2.3.4', '255.255.255.255', '10.0.0.1', '2001:db8:85a3:8d3:1319:8a2e:370:7348', '::1', 'fe80::1', '::ffff:192.0.2.128', '2001:db8::', 'not-an-ip', '', '999.1.1.1');
$wp_dirs = array_filter(array(getenv('EFB_WP_DIR'), '/tmp/claude-0/-home-user/6470513e-3182-5834-893e-38f1388be77b/scratchpad/i18n-tools/wordpress'));
$wp_fn = null;
foreach ($wp_dirs as $d) {
	$f = rtrim($d, '/') . '/wp-includes/functions.php';
	if (is_readable($f) && preg_match('/function wp_privacy_anonymize_ip\(.*?\n}\n/s', file_get_contents($f), $m)) { $wp_fn = $m[0]; break; }
}
$fallback = array();
foreach ($vectors as $v) $fallback[$v] = Privacy::anonymize_ip($v);
t('fallback: 1.2.3.4 -> 1.2.3.0', $fallback['1.2.3.4'], '1.2.3.0');
t('fallback: IPv6 -> /48 (not the /64 WordPress keeps)', $fallback['2001:db8:85a3:8d3:1319:8a2e:370:7348'], '2001:db8:85a3::');
t('fallback: IPv6 /48 is idempotent', Privacy::anonymize_ip('2001:db8:85a3::'), '2001:db8:85a3::');
t('fallback: anonymizing an old /64 value gives the /48', Privacy::anonymize_ip('2001:db8:85a3:8d3::'), '2001:db8:85a3::');
t('fallback: ::1 -> ::', $fallback['::1'], '::');
t('fallback: IPv4-mapped IPv6 keeps WordPress behaviour', $fallback['::ffff:192.0.2.128'], '::ffff:192.0.2.0');
t('fallback: IPv4 last octet zeroed', Privacy::anonymize_ip('1.2.3.4'), '1.2.3.0');
t('fallback: empty stays empty', $fallback[''], '');
// The /48 of WordPress' own result, i.e. what EFB must return for each vector.
$to48 = function ($wp) {
	if ($wp === '' || strpos($wp, ':') === false || strpos($wp, '.') !== false) return $wp;
	$b = @inet_pton($wp);
	return ($b === false || strlen($b) !== 16) ? $wp : inet_ntop(substr($b, 0, 6) . str_repeat("\0", 10));
};
if ($wp_fn) {
	eval(str_replace('function wp_privacy_anonymize_ip(', 'function wp_privacy_anonymize_ip_ref(', $wp_fn));
	$diff = array();
	foreach ($vectors as $v) {
		if ($v === '') continue; // WordPress returns '0.0.0.0' for ''; EFB keeps "no address" empty.
		$ref = $to48(wp_privacy_anonymize_ip_ref($v));
		if ($ref !== $fallback[$v]) $diff[$v] = array($fallback[$v], $ref);
	}
	t('fallback equals WordPress wp_privacy_anonymize_ip() (IPv6 cut to /48) on ' . (count($vectors) - 1) . ' vectors', $diff, array());
	eval(str_replace('function wp_privacy_anonymize_ip(', 'function wp_privacy_anonymize_ip(', $wp_fn));
	t('with WordPress loaded, anonymize_ip uses it', Privacy::anonymize_ip('9.8.7.6'), '9.8.7.0');
	t('with WordPress loaded, IPv6 still cut to /48', Privacy::anonymize_ip('2001:db8:85a3:8d3:1319:8a2e:370:7348'), '2001:db8:85a3::');
} else {
	echo "[SKIP] WordPress copy not found: parity with wp_privacy_anonymize_ip() not checked\n";
}

// ------------------------------------------------------------------ 3. client_ip
t('direct visitor: Client-IP header ignored', Privacy::client_ip(array('REMOTE_ADDR' => '198.51.100.7', 'HTTP_CLIENT_IP' => '6.6.6.6')), '198.51.100.7');
t('direct visitor: X-Forwarded-For ignored', Privacy::client_ip(array('REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6')), '198.51.100.7');
t('local proxy: X-Forwarded-For used', Privacy::client_ip(array('REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.9')), '198.51.100.9');
t('local proxy: spoofed left-most XFF entry ignored (right-most untrusted wins)', Privacy::client_ip(array('REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.9')), '198.51.100.9');
t('local proxy behind Cloudflare: XFF skips the Cloudflare hop', Privacy::client_ip(array('REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.9, 104.16.1.1')), '198.51.100.9');
t('Cloudflare edge: CF-Connecting-IP used', Privacy::client_ip(array('REMOTE_ADDR' => '172.70.1.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.10', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6')), '198.51.100.10');
t('CF-Connecting-IP from a non-Cloudflare client ignored', Privacy::client_ip(array('REMOTE_ADDR' => '198.51.100.7', 'HTTP_CF_CONNECTING_IP' => '6.6.6.6')), '198.51.100.7');
t('local proxy: garbage XFF falls back to REMOTE_ADDR', Privacy::client_ip(array('REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '<script>')), '127.0.0.1');
t('local proxy: "IP:port" in XFF', Privacy::client_ip(array('REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.9:5050')), '198.51.100.9');
t('local proxy: "[IPv6]:port" in XFF', Privacy::client_ip(array('REMOTE_ADDR' => '::1', 'HTTP_X_FORWARDED_FOR' => '[2001:db8::5]:443')), '2001:db8::5');
t('local proxy: X-Real-IP when no XFF', Privacy::client_ip(array('REMOTE_ADDR' => '192.168.1.1', 'HTTP_X_REAL_IP' => '198.51.100.11')), '198.51.100.11');
t('no REMOTE_ADDR (CLI / cron) -> 1.1.1.1 as before', Privacy::client_ip(array()), '1.1.1.1');
t('IPv4 CIDR match', Privacy::ip_in_range('172.31.255.255', '172.16.0.0/12'), true);
t('IPv4 CIDR miss', Privacy::ip_in_range('172.32.0.1', '172.16.0.0/12'), false);
t('IPv6 CIDR match', Privacy::ip_in_range('2606:4700:10::1', '2606:4700::/32'), true);
t('IPv6 vs IPv4 range never matches', Privacy::ip_in_range('::1', '127.0.0.0/8'), false);
$GLOBALS['test_filters']['efb_trusted_proxies'] = function () { return array(); };
t('efb_trusted_proxies = [] trusts no header', Privacy::client_ip(array('REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.9')), '127.0.0.1');
$GLOBALS['test_filters']['efb_trusted_proxies'] = function () { return array('198.51.100.0/24'); };
t('efb_trusted_proxies with a public proxy', Privacy::client_ip(array('REMOTE_ADDR' => '198.51.100.200', 'HTTP_X_FORWARDED_FOR' => '203.0.113.5')), '203.0.113.5');
unset($GLOBALS['test_filters']['efb_trusted_proxies']);
// Every copy of get_ip_address() delegates to Privacy::client_ip().
foreach (array('includes/class-Emsfb-public.php', 'includes/functions.php', 'includes/class-email-handler.php', 'includes/admin/class-Emsfb-admin.php') as $f) {
	$src = file_get_contents(__DIR__ . '/../' . $f);
	preg_match('/function get_ip_address\(\) \{(.*?)\n\s*\}/s', $src, $m);
	t("{$f}: get_ip_address() delegates to Privacy::client_ip()", isset($m[1]) && strpos($m[1], 'Privacy::client_ip()') !== false && strpos($m[1], 'HTTP_X_FORWARDED_FOR') === false, true);
}

// ------------------------------------------------------------------ 4. per-form keys and the map
$rows = array(
	array('type' => 'payment', 'prv_nostore' => '1', 'prv_ip' => 'ANON', 'prv_days' => '۹۰', 'prv_id_field' => 'em1'),
	array('id_' => 'em1', 'type' => 'email', 'prv_pii' => '1'),
	array('id_' => 'tx1', 'type' => 'text', 'prv_pii' => 'x'),
);
$n = Privacy::normalize_form_settings($rows);
t('prv_nostore refused for a payment form', $n[0]['prv_nostore'], '0');
t('prv_ip allow-listed (case folded)', $n[0]['prv_ip'], 'anon');
t('prv_days from Persian digits', $n[0]['prv_days'], '90');
t('prv_id_field kept when it is an email field', $n[0]['prv_id_field'], 'em1');
t('prv_pii "1" kept', $n[1]['prv_pii'], '1');
t('prv_pii invalid -> ""', $n[2]['prv_pii'], '');
$rows[0]['type'] = 'form'; $rows[0]['prv_id_field'] = 'tx1'; $rows[0]['prv_days'] = '99999'; $rows[0]['prv_ip'] = '<b>';
$n = Privacy::normalize_form_settings($rows);
t('prv_nostore kept for a form', $n[0]['prv_nostore'], '1');
t('prv_id_field that is not an email field -> ""', $n[0]['prv_id_field'], '');
t('prv_days capped at 3650', $n[0]['prv_days'], '3650');
t('prv_ip invalid -> "" (global)', $n[0]['prv_ip'], '');
t('subscribe may skip storage', Privacy::normalize_form_settings(array(array('type' => 'subscribe', 'prv_nostore' => 1)))[0]['prv_nostore'], '1');
foreach (array('survey', 'register', 'login') as $ty) {
	t("{$ty} may not skip storage", Privacy::normalize_form_settings(array(array('type' => $ty, 'prv_nostore' => '1')))[0]['prv_nostore'], '0');
}
t('absent keys stay absent (old form)', Privacy::normalize_form_settings(array(array('type' => 'form', 'formName' => 'x'))), array(array('type' => 'form', 'formName' => 'x')));
t('prv_days "" stays "" (global)', Privacy::normalize_form_settings(array(array('type' => 'form', 'prv_days' => '')))[0]['prv_days'], '');
t('prv_days "0" stays "0" (never for this form)', Privacy::normalize_form_settings(array(array('type' => 'form', 'prv_days' => '0')))[0]['prv_days'], '0');

$GLOBALS['opts'] = array();
Privacy::on_form_saved(12, array(array('type' => 'form', 'prv_ip' => 'none')), array());
t('a form with an override enters the map', Privacy::form_map(), array(12 => array('days' => '', 'ip' => 'none', 'nostore' => 0, 'id_field' => '')));
t('the map is not autoloaded', $GLOBALS['autoload']['emsfb_prv_forms'], false);
Privacy::on_form_saved(13, array((object) array('type' => 'form', 'prv_days' => '30', 'prv_nostore' => '1')), array());
t('object rows (render path) are read too', Privacy::form_entry(13), array('days' => '30', 'ip' => '', 'nostore' => 1, 'id_field' => ''));
Privacy::on_form_saved(12, array(array('type' => 'form', 'prv_ip' => '')), array());
t('back to defaults leaves the map', array_keys(Privacy::form_map()), array(13));
Privacy::on_form_deleted(13);
t('deleted form leaves the map', Privacy::form_map(), array());

// ------------------------------------------------------------------ 5. sanitize_setting
t('prv_ip_mode allow-list', array(Privacy::sanitize_setting('prv_ip_mode', 'NONE'), Privacy::sanitize_setting('prv_ip_mode', 'x')), array('none', 'store'));
t('prv_days absint + cap + digits', array(Privacy::sanitize_setting('prv_days', '٣٠'), Privacy::sanitize_setting('prv_days', '-5'), Privacy::sanitize_setting('prv_days', '5000'), Privacy::sanitize_setting('prv_days', 'abc')), array('30', '5', '3650', '0'));
t('prv_action / prv_erase_mode allow-list', array(Privacy::sanitize_setting('prv_action', 'anon'), Privacy::sanitize_setting('prv_erase_mode', 'drop')), array('anon', 'delete'));
t('prv_files boolean -> "1" / ""', array(Privacy::sanitize_setting('prv_files', true), Privacy::sanitize_setting('prv_files', false), Privacy::sanitize_setting('prv_files', 'true')), array('1', '', '1'));
t('prv_nostore_file_days 1..365', array(Privacy::sanitize_setting('prv_nostore_file_days', '0'), Privacy::sanitize_setting('prv_nostore_file_days', '999'), Privacy::sanitize_setting('prv_nostore_file_days', '')), array('1', '365', '30'));
t('not a privacy key -> null', Privacy::sanitize_setting('siteKey', 'x'), null);
t('setting() default when absent', Privacy::setting('prv_days', new stdClass()), '0');

// ------------------------------------------------------------------ 6. set_settings_Emsfb
eval('namespace Emsfb; class Builder_Api { static function drop_unread_setting_markers($m, $ac) { return $m; } }');
function emsfb_json_decode_efb($s, $assoc = false) { return json_decode($s, $assoc); }
function emsfb_json_normalize_efb($s) { return $s; }
class SetFn {
	public $saved = null;
	public function text_efb($k) { $o = array(); foreach ((array) $k as $x) $o[$x] = 'T:' . $x; return $o; }
	public function user_permission_efb_admin_dashboard() { return true; }
	public function set_setting_Emsfb($json, $email) { $this->saved = json_decode($json, true); }
	public function sanitize_full_html_efb($v) { return $v; }
}
$GLOBALS['setfn'] = new SetFn();
function get_efbFunction() { return $GLOBALS['setfn']; }
require __DIR__ . '/../includes/admin/class-Emsfb-admin.php';
$GLOBALS["wpdb"] = (object) array("prefix" => "wp_");
$admin = (new ReflectionClass('\Emsfb\Admin'))->newInstanceWithoutConstructor();
$run_settings = function ($post, $stored) use ($admin) {
	$GLOBALS['settings'] = (object) $stored;
	$_POST = array('message' => addslashes(json_encode($post, JSON_UNESCAPED_UNICODE)));
	$GLOBALS['setfn']->saved = null;
	try { $admin->set_settings_Emsfb(); } catch (EfbStop $e) {}
	return $GLOBALS['setfn']->saved;
};
$GLOBALS['caps']['manage_options'] = true;
$saved = $run_settings(array('emailSupporter' => 'a@example.test', 'prv_ip_mode' => 'none', 'prv_days' => '۴۵', 'prv_files' => false, 'prv_action' => 'evil'), array('prv_ip_mode' => 'store'));
t('administrator: prv_ip_mode saved', $saved['prv_ip_mode'], 'none');
t('administrator: prv_days digits normalised', $saved['prv_days'], '45');
t('administrator: prv_files false -> ""', $saved['prv_files'], '');
t('administrator: prv_action outside the allow-list -> delete', $saved['prv_action'], 'delete');
$GLOBALS['caps']['manage_options'] = false;
$saved = $run_settings(array('emailSupporter' => 'a@example.test', 'prv_ip_mode' => 'store', 'prv_days' => '1'), array('prv_ip_mode' => 'none', 'prv_days' => '90'));
t('plugin role without manage_options: prv_ip_mode kept', $saved['prv_ip_mode'], 'none');
t('plugin role without manage_options: prv_days kept', $saved['prv_days'], '90');
$GLOBALS['caps']['manage_options'] = true;
$saved = $run_settings(array('emailSupporter' => 'a@example.test'), array('prv_ip_mode' => 'anon', 'prv_days' => '30'));
t('a screen that does not send prv_* keeps them', array($saved['prv_ip_mode'], $saved['prv_days']), array('anon', '30'));
$saved = $run_settings(array('emailSupporter' => 'a@example.test'), array());
t('a site without prv_* gets none added (defaults apply on read)', isset($saved['prv_ip_mode']), false);

// ------------------------------------------------------------------ 7. purge AJAX
class PurgeDb {
	public $prefix = 'wp_';
	public $t = array();
	public function prepare($q) { $a = array_slice(func_get_args(), 1); foreach ($a as $x) $q = preg_replace('/%s|%d/', is_int($x) ? (string) $x : "'" . $x . "'", $q, 1); return $q; }
	public function get_col($q) {
		if (preg_match("/SELECT `(\w+)` FROM `wp_(\w+)` WHERE `(\w+)` <> '' LIMIT (\d+)/", $q, $m)) {
			$out = array(); foreach ($this->t[$m[2]] as $id => $r) { if ($r[$m[3]] !== '') $out[] = $id; if (count($out) >= (int) $m[4]) break; } return $out;
		}
		if (preg_match("/SELECT DISTINCT `(\w+)` FROM `wp_(\w+)` WHERE `\w+` <> '' AND `\w+` NOT LIKE '%\.0' AND `\w+` NOT LIKE '%::' LIMIT (\d+)/", $q, $m)) {
			$out = array(); foreach ($this->t[$m[2]] as $r) { $v = $r[$m[1]]; if ($v !== '' && substr($v, -2) !== '.0' && substr($v, -2) !== '::') $out[$v] = $v; } return array_values($out);
		}
		throw new Exception('unexpected ' . $q);
	}
	public function query($q) {
		if (preg_match("/UPDATE `wp_(\w+)` SET (.*) WHERE `(\w+)` IN \(([\d,]+)\)/", $q, $m)) {
			preg_match_all("/`(\w+)` = ''/", $m[2], $cols);
			foreach (explode(',', $m[4]) as $id) foreach ($cols[1] as $c) $this->t[$m[1]][(int) $id][$c] = '';
			return count(explode(',', $m[4]));
		}
		if (preg_match("/UPDATE `wp_(\w+)` SET `(\w+)` = '([^']*)' WHERE `\w+` = '([^']*)'/", $q, $m)) {
			$n = 0; foreach ($this->t[$m[1]] as $id => $r) if ($r[$m[2]] === $m[4]) { $this->t[$m[1]][$id][$m[2]] = $m[3]; $n++; } return $n;
		}
		throw new Exception('unexpected ' . $q);
	}
}
$fresh = function () {
	$db = new PurgeDb();
	$db->t = array(
		'emsfb_msg_'  => array(1 => array('ip' => '1.2.3.4'), 2 => array('ip' => ''), 3 => array('ip' => '2001:db8:1:2::9')),
		'emsfb_rsp_'  => array(1 => array('ip' => '5.6.7.8', 'reader_ip' => '9.9.9.9')),
		'emsfb_stts_' => array(1 => array('ip' => '1.2.3.4', 'os' => 'Linux', 'browser' => 'Chrome')),
	);
	return $db;
};
$GLOBALS['wpdb'] = $fresh();
$GLOBALS['caps']['manage_options'] = false;
$_POST = array('mode' => 'none');
try { Privacy::ajax_purge_ip(); $res = null; } catch (EfbStop $e) { $res = $e->payload; }
t('purge without manage_options is refused', isset($res['success']) ? $res['success'] : null, false);
t('...and nothing changed', $GLOBALS['wpdb']->t['emsfb_msg_'][1]['ip'], '1.2.3.4');
$GLOBALS['caps']['manage_options'] = true;
try { Privacy::ajax_purge_ip(); } catch (EfbStop $e) { $res = $e->payload; }
t('purge none: done', array($res['success'], $res['done']), array(true, true));
t('purge none: msg, rsp (ip, reader_ip) and stts (ip, os, browser) emptied', array(
	$GLOBALS['wpdb']->t['emsfb_msg_'][1]['ip'], $GLOBALS['wpdb']->t['emsfb_msg_'][3]['ip'], $GLOBALS['wpdb']->t['emsfb_rsp_'][1]['ip'], $GLOBALS['wpdb']->t['emsfb_rsp_'][1]['reader_ip'],
	$GLOBALS['wpdb']->t['emsfb_stts_'][1]['ip'], $GLOBALS['wpdb']->t['emsfb_stts_'][1]['os'], $GLOBALS['wpdb']->t['emsfb_stts_'][1]['browser']), array('', '', '', '', '', '', ''));
$GLOBALS['wpdb'] = $fresh();
$_POST = array('mode' => 'anon');
try { Privacy::ajax_purge_ip(); } catch (EfbStop $e) { $res = $e->payload; }
t('purge anon: addresses anonymized, browser kept', array(
	$GLOBALS['wpdb']->t['emsfb_msg_'][1]['ip'], $GLOBALS['wpdb']->t['emsfb_msg_'][3]['ip'], $GLOBALS['wpdb']->t['emsfb_rsp_'][1]['reader_ip'], $GLOBALS['wpdb']->t['emsfb_stts_'][1]['browser']),
	array('1.2.3.0', '2001:db8:1::', '9.9.9.0', 'Chrome'));
t('purge anon reports the changed rows', $res['changed'], 5);
try { Privacy::ajax_purge_ip(); } catch (EfbStop $e) { $res = $e->payload; }
t('purge anon twice changes nothing more', $res['changed'], 0);

// ------------------------------------------------------------------ 8. stored IP paths, trace, response
$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
t("submit answers no longer carry the visitor's IP", preg_match('/\$response = [^;]*\'ip\' =>/', $pub), 0);
t('submit decides the IP mode from the stored form', strpos($pub, '$this->ip = \Emsfb\Privacy::store_ip($this->get_ip_address(), $this->id);') !== false, true);
t('insert_message_db() passes ip through store_ip()', strpos($pub, "'ip' => \\Emsfb\\Privacy::store_ip(\$this->ip, \$this->id),") !== false, true);
t('reply IP follows the entry form', strpos($pub, 'Privacy::store_ip($raw_ip_efb,') !== false, true);
t('trace of a Human Shield block logs log_ip()', strpos($pub, "'ip'      => \\Emsfb\\Privacy::log_ip(\$this->get_ip_address(), intval(\$this->id)),") !== false, true);
$fns = file_get_contents(__DIR__ . '/../includes/functions.php');
preg_match('/public function efb_code_validate_create\(.*?\n\t\}/s', $fns, $m);
t('session rows: store_ip + no os/browser on none', isset($m[0]) && strpos($m[0], 'Privacy::store_ip($ip, $fid)') !== false && strpos($m[0], 'store_agent($fid)') !== false, true);
t('dead "sips" phrase removed', strpos($fns, '"sips" =>'), false);
$adm = file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-admin.php');
t('admin reply IP follows the entry form', strpos($adm, 'Privacy::store_ip($this->ip, $form_id_efb)') !== false, true);
// Email_Trace::redact_pii() anonymizes ip keys
require __DIR__ . '/../includes/class-Emsfb-email-trace.php';
$rp = new ReflectionMethod('\Emsfb\Email_Trace', 'redact_pii');
$rp->setAccessible(true);
$red = $rp->invoke(null, array('gate' => 'x', 'ip' => '198.51.100.23', 'nested' => array('reader_ip' => '2001:db8:1:2::1'), 'note' => 'ip unchanged'));
t('trace: ip key anonymized', $red['ip'], '198.51.100.0');
t('trace: nested reader_ip anonymized', $red['nested']['reader_ip'], '2001:db8:1::');
t('trace: other values untouched', $red['note'], 'ip unchanged');
// Registry
$api = file_get_contents(__DIR__ . '/../includes/class-Emsfb-builder-api.php');
t('registry: setting:privacy_ip (free, prv_ip)', strpos($api, "'setting:privacy_ip'             => array('free', null, array('prv_ip'), 'prvIpMode')") !== false, true);
t('registry: setting:privacy_site lists prv_ip_mode', (bool) preg_match("/'setting:privacy_site'.*?'settings_keys' => array\('prv_ip_mode'/s", $api), true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
