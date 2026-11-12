<?php
/**
 * Not a test. The PAY00 tests' database: the F13 SQLite $wpdb stand-in (privacy-test-db.php) plus
 * the payment tables, created from the real Store::ddl() (MySQL DDL turned into SQLite DDL here),
 * and INSERT IGNORE / NOW() support. Also the WordPress functions the payment classes call.
 */
require_once __DIR__ . '/privacy-test-db.php';

class EfbPayTestDb extends EfbPrivacyTestDb {
	public function __construct() {
		parent::__construct();
		$this->pdo->exec('CREATE TABLE wp_options (option_id INTEGER PRIMARY KEY AUTOINCREMENT, option_name TEXT UNIQUE, option_value TEXT, autoload TEXT)');
	}

	public function get_charset_collate() { return ''; }

	/** Store::ddl() → SQLite (types loosened, KEY lines → CREATE INDEX). */
	public function create_payment_tables() {
		foreach (\Emsfb\Payments\Store::ddl($this->prefix, '') as $short => $sql) {
			$this->exec_mysql_ddl($sql);
		}
	}

	public function exec_mysql_ddl($sql) {
		preg_match('/CREATE TABLE (\S+) \((.*)\)\s*;?\s*$/s', trim($sql), $m);
		$table = $m[1];
		$cols = array();
		$idx = array();
		foreach (preg_split('/,\n/', trim($m[2])) as $line) {
			$line = trim($line);
			if (preg_match('/^PRIMARY KEY\s+\((\w+)\)/', $line)) continue;
			if (preg_match('/^(UNIQUE )?KEY (\w+) \(([^)]+)\)/', $line, $k)) { $idx[] = 'CREATE ' . ($k[1] ? 'UNIQUE ' : '') . "INDEX {$table}_{$k[2]} ON {$table} ({$k[3]})"; continue; }
			if (preg_match('/^(\w+) .*AUTO_INCREMENT/', $line, $c)) { $cols[] = "{$c[1]} INTEGER PRIMARY KEY AUTOINCREMENT"; continue; }
			$line = preg_replace('/\b(bigint|int|smallint|tinyint)\(\d+\)( unsigned)?/i', 'INTEGER', $line);
			$line = preg_replace('/\b(varchar|char)\(\d+\)/i', 'TEXT', $line);
			$line = preg_replace('/\b(mediumtext|datetime)\b/i', 'TEXT', $line);
			$cols[] = $line;
		}
		$this->pdo->exec("CREATE TABLE {$table} (" . implode(', ', $cols) . ')');
		foreach ($idx as $q) $this->pdo->exec($q);
	}

	public function query($q) {
		$q = preg_replace('/^\s*INSERT IGNORE/i', 'INSERT OR IGNORE', $q);
		return parent::query($q);
	}
}

/* WordPress functions the payment classes use. Each is defined only when missing. */
$GLOBALS['opts'] = isset($GLOBALS['opts']) ? $GLOBALS['opts'] : array();
if (!function_exists('get_option')) { function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; } }
if (!function_exists('update_option')) { function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; } }
if (!function_exists('add_option')) { function add_option($k, $v = '', $d = '', $a = null) { if (array_key_exists($k, $GLOBALS['opts'])) return false; $GLOBALS['opts'][$k] = $v; return true; } }
if (!function_exists('home_url')) { function home_url($p = '') { return 'https://site.example' . $p; } }
if (!function_exists('rest_url')) { function rest_url($p = '') { return 'https://site.example/wp-json/' . ltrim($p, '/'); } }
if (!function_exists('wp_salt')) { function wp_salt($s = 'auth') { return 'unit-test-salt-' . $s; } }
if (!function_exists('is_wp_error')) { function is_wp_error($x) { return $x instanceof WP_Error; } }
if (!class_exists('WP_Error')) { class WP_Error { public $c; public $m; public function __construct($c = '', $m = '') { $this->c = $c; $this->m = $m; } public function get_error_code() { return $this->c; } public function get_error_message() { return $this->m; } public function get_error_data() { return array(); } } }
if (!function_exists('add_query_arg')) {
	function add_query_arg($args, $url) {
		$sep = strpos($url, '?') === false ? '?' : '&';
		return $url . $sep . http_build_query($args);
	}
}
if (!function_exists('wp_parse_url')) { function wp_parse_url($u, $c = -1) { return $c === -1 ? parse_url($u) : parse_url($u, $c); } }
if (!function_exists('esc_html__')) { function esc_html__($s, $d = null) { return $s; } }
if (!function_exists('__')) { function __($s, $d = null) { return $s; } }
if (!function_exists('esc_html')) { function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); } }
if (!function_exists('esc_attr')) { function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); } }
if (!function_exists('esc_url')) { function esc_url($s) { return (string) $s; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s) { return is_scalar($s) ? trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))) : ''; } }
if (!function_exists('sanitize_key')) { function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); } }
if (!function_exists('sanitize_email')) { function sanitize_email($s) { return filter_var((string) $s, FILTER_VALIDATE_EMAIL) ? (string) $s : ''; } }
if (!function_exists('is_email')) { function is_email($s) { return (bool) filter_var((string) $s, FILTER_VALIDATE_EMAIL); } }
if (!function_exists('apply_filters')) { function apply_filters($h, $v) { $a = func_get_args(); if (isset($GLOBALS['efb_filters'][$h])) { foreach ($GLOBALS['efb_filters'][$h] as $cb) { $a[1] = call_user_func_array($cb, array_slice($a, 1)); } } return $a[1]; } }
if (!function_exists('add_filter')) { function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['efb_filters'][$h][] = $cb; return true; } }
if (!function_exists('do_action')) { function do_action($h) { $a = array_slice(func_get_args(), 1); $GLOBALS['efb_actions_log'][] = array($h, $a); if (isset($GLOBALS['efb_hooks'][$h])) foreach ($GLOBALS['efb_hooks'][$h] as $cb) call_user_func_array($cb, $a); } }
if (!function_exists('add_action')) { function add_action($h, $cb, $p = 10, $n = 1) { $GLOBALS['efb_hooks'][$h][] = $cb; return true; } }
if (!function_exists('has_action')) { function has_action($h) { return !empty($GLOBALS['efb_hooks'][$h]); } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v, $f = 0) { return json_encode($v, $f); } }
if (!function_exists('current_time')) { function current_time($t, $g = 0) { return gmdate('Y-m-d H:i:s'); } }
if (!function_exists('get_locale')) { function get_locale() { return isset($GLOBALS['efb_locale']) ? $GLOBALS['efb_locale'] : 'en_US'; } }
if (!function_exists('determine_locale')) { function determine_locale() { return get_locale(); } }
if (!function_exists('wp_validate_redirect')) { function wp_validate_redirect($l, $d = '') { $h = parse_url($l, PHP_URL_HOST); return $h === 'site.example' ? $l : $d; } }
if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event($t, $h, $a = array()) { $GLOBALS['efb_cron'][] = array($t, $h, $a); return true; } }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled($h, $a = array()) { return false; } }

/**
 * A recording pre_http_request: wp_remote_request() calls $GLOBALS['efb_http_handler']($url, $args)
 * which returns ['code' => int, 'body' => string|array] or a WP_Error. Every request is logged.
 */
$GLOBALS['efb_http_log'] = array();
if (!function_exists('wp_remote_request')) {
	function wp_remote_request($url, $args = array()) {
		$GLOBALS['efb_http_log'][] = array('url' => $url, 'args' => $args);
		$pre = apply_filters('pre_http_request', false, $args, $url);
		if ($pre !== false) return $pre;
		$h = isset($GLOBALS['efb_http_handler']) ? $GLOBALS['efb_http_handler'] : null;
		if (!$h) return new WP_Error('no_network', 'tests have no network');
		$r = $h($url, $args);
		if ($r instanceof WP_Error) return $r;
		return array('response' => array('code' => $r['code']), 'body' => is_string($r['body']) ? $r['body'] : json_encode($r['body']), 'headers' => isset($r['headers']) ? $r['headers'] : array());
	}
	function wp_remote_retrieve_response_code($r) { return is_array($r) ? $r['response']['code'] : 0; }
	function wp_remote_retrieve_body($r) { return is_array($r) ? $r['body'] : ''; }
	function wp_remote_retrieve_headers($r) { return is_array($r) ? $r['headers'] : array(); }
}

/** Loads every payment class (the bootstrap without its hooks). */
function efb_pay_test_load() {
	$d = __DIR__ . '/../includes/payments/';
	foreach (array('class-Emsfb-payment-http.php', 'class-Emsfb-payment-store.php', 'class-Emsfb-payment-money.php', 'class-Emsfb-payment-secrets.php',
		'class-Emsfb-payment-gateway.php', 'class-Emsfb-payment-registry.php', 'class-Emsfb-payment-pricing.php', 'payment-field-functions-efb.php',
		'class-Emsfb-payment-service.php') as $f) {
		if (is_readable($d . $f)) require_once $d . $f;
	}
}

function efb_pay_t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
