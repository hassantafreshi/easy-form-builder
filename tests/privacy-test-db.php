<?php
/**
 * Not a test. A small $wpdb stand-in on an in-memory SQLite database (PDO), for the F13 privacy
 * tests (test-privacy-retention.php, test-privacy-exporter.php, test-privacy-nostore-submit.php):
 * the plugin's real SQL runs against real tables instead of a hand-written fake.
 *
 * Supports what Privacy and Upload_Guard use: prepare (%d %s %f, list or variadic args), query,
 * get_results (OBJECT / ARRAY_A), get_col, get_var, get_row, insert, update, delete, esc_like,
 * SHOW COLUMNS ... LIKE, SHOW TABLES LIKE. LIKE gets ESCAPE '\' as in MySQL.
 */
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('OBJECT')) define('OBJECT', 'OBJECT');

class EfbPrivacyTestDb {
	public $prefix = 'wp_';
	public $options = 'wp_options';
	public $last_error = '';
	public $insert_id = 0;
	public $queries = array();
	/** @var PDO */
	public $pdo;

	public function __construct() {
		$this->pdo = new PDO('sqlite::memory:');
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		$this->pdo->exec('CREATE TABLE wp_emsfb_form (form_id INTEGER PRIMARY KEY AUTOINCREMENT, form_name TEXT, form_structer TEXT, form_type TEXT DEFAULT "form")');
		$this->pdo->exec('CREATE TABLE wp_emsfb_msg_ (msg_id INTEGER PRIMARY KEY AUTOINCREMENT, form_id INTEGER, track TEXT, ip TEXT, form_title_x TEXT DEFAULT "", content TEXT, date TEXT, read_date TEXT, read_ INTEGER DEFAULT 0, read_by TEXT DEFAULT "", uid INTEGER NOT NULL DEFAULT 0, prv_state INTEGER NOT NULL DEFAULT 0)');
		$this->pdo->exec('CREATE TABLE wp_emsfb_rsp_ (rsp_id INTEGER PRIMARY KEY AUTOINCREMENT, msg_id INTEGER, ip TEXT, content TEXT, date TEXT, read_by TEXT DEFAULT "", read_date TEXT, read_ INTEGER DEFAULT 0, reader_ip TEXT DEFAULT "", rsp_by INTEGER DEFAULT 0)');
		$this->pdo->exec('CREATE TABLE wp_emsfb_stts_ (id INTEGER PRIMARY KEY AUTOINCREMENT, sid TEXT, fid INTEGER, ip TEXT, os TEXT, browser TEXT, status TEXT)');
	}

	public function esc_like($text) { return addcslashes((string) $text, '_%\\'); }

	public function prepare($query, ...$args) {
		if (count($args) === 1 && is_array($args[0])) $args = $args[0];
		$i = 0;
		$pdo = $this->pdo;
		return preg_replace_callback('/%[dsf]/', function ($m) use (&$i, $args, $pdo) {
			$v = array_key_exists($i, $args) ? $args[$i] : '';
			$i++;
			if ($m[0] === '%d') return (string) intval($v);
			if ($m[0] === '%f') return (string) floatval($v);
			return $pdo->quote((string) $v);
		}, $query);
	}

	private function sql($q) {
		$q = preg_replace("/LIKE ('(?:[^']|'')*')/", "LIKE $1 ESCAPE '\\'", $q);
		return $q;
	}

	private function run($q) {
		$this->queries[] = $q;
		if (preg_match('/^\s*SHOW COLUMNS FROM `?(\w+)`? LIKE \'([^\']+)\'/i', $q, $m)) {
			$rows = array();
			foreach ($this->pdo->query('PRAGMA table_info(' . $m[1] . ')')->fetchAll(PDO::FETCH_ASSOC) as $c) {
				if ($c['name'] === $m[2]) $rows[] = array('Field' => $c['name']);
			}
			return $rows;
		}
		if (preg_match('/^\s*SHOW TABLES LIKE \'([^\']+)\'/i', $q, $m)) {
			$st = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name = " . $this->pdo->quote($m[1]));
			return $st ? array_map(function ($r) { return array('name' => $r['name']); }, $st->fetchAll(PDO::FETCH_ASSOC)) : array();
		}
		$st = $this->pdo->query($this->sql($q));
		if ($st === false) {
			$e = $this->pdo->errorInfo();
			$this->last_error = (string) $e[2];
			return false;
		}
		$this->last_error = '';
		return $st;
	}

	public function query($q) {
		$st = $this->run($q);
		if ($st === false) return false;
		if (is_array($st)) return count($st);
		return preg_match('/^\s*SELECT/i', $q) ? count($st->fetchAll()) : $st->rowCount();
	}

	public function get_results($q, $output = OBJECT) {
		$st = $this->run($q);
		if ($st === false) return array();
		$rows = is_array($st) ? $st : $st->fetchAll(PDO::FETCH_ASSOC);
		return $output === ARRAY_A ? $rows : array_map(function ($r) { return (object) $r; }, $rows);
	}

	public function get_row($q, $output = OBJECT) {
		$r = $this->get_results($q, $output);
		return $r ? $r[0] : null;
	}

	public function get_col($q) {
		return array_map(function ($r) { return reset($r); }, $this->get_results($q, ARRAY_A));
	}

	public function get_var($q) {
		$r = $this->get_results($q, ARRAY_A);
		return $r ? reset($r[0]) : null;
	}

	public function insert($table, $data) {
		$cols = array_keys($data);
		$q = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_map(array($this->pdo, 'quote'), array_map('strval', array_values($data)))) . ')';
		$n = $this->query($q);
		if ($n === false) return false;
		$this->insert_id = (int) $this->pdo->lastInsertId();
		return $n;
	}

	public function update($table, $data, $where) {
		$set = array();
		foreach ($data as $k => $v) $set[] = $k . ' = ' . $this->pdo->quote((string) $v);
		$w = array();
		foreach ($where as $k => $v) $w[] = $k . ' = ' . $this->pdo->quote((string) $v);
		return $this->query('UPDATE ' . $table . ' SET ' . implode(', ', $set) . ' WHERE ' . implode(' AND ', $w));
	}

	public function delete($table, $where) {
		$w = array();
		foreach ($where as $k => $v) $w[] = $k . ' = ' . $this->pdo->quote((string) $v);
		return $this->query('DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $w));
	}

	/* Fixtures */
	public function add_form($rows, $type = 'form') {
		$json = str_replace('"', '\\"', json_encode($rows, JSON_UNESCAPED_UNICODE));
		$this->insert('wp_emsfb_form', array('form_name' => 'F', 'form_structer' => $json, 'form_type' => $type));
		return $this->insert_id;
	}
	public function add_entry($form_id, $rows, $date, $extra = array()) {
		$json = str_replace('"', '\\"', json_encode($rows, JSON_UNESCAPED_UNICODE));
		$this->insert('wp_emsfb_msg_', array_merge(array('form_id' => $form_id, 'track' => 'T' . mt_rand(1000, 99999), 'ip' => '203.0.113.5', 'content' => $json, 'date' => $date, 'read_' => 0), $extra));
		return $this->insert_id;
	}
	public function add_reply($msg_id, $rows, $date, $extra = array()) {
		$json = str_replace('"', '\\"', json_encode($rows, JSON_UNESCAPED_UNICODE));
		$this->insert('wp_emsfb_rsp_', array_merge(array('msg_id' => $msg_id, 'ip' => '203.0.113.6', 'content' => $json, 'date' => $date, 'reader_ip' => '203.0.113.7'), $extra));
		return $this->insert_id;
	}
	public function count($table, $where = '1=1') { return (int) $this->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}"); }
	public function row($table, $where) { return $this->get_row("SELECT * FROM {$table} WHERE {$where}", ARRAY_A); }
}

/** WordPress functions the privacy code needs, file system ones on a temporary uploads folder. */
function efb_privacy_test_wp_stubs($uploads_dir, $uploads_url = 'http://example.test/wp-content/uploads') {
	$GLOBALS['efb_uploads'] = array('basedir' => $uploads_dir, 'baseurl' => $uploads_url);
}
if (!function_exists('wp_upload_dir')) { function wp_upload_dir($t = null, $c = true, $r = false) { return array('basedir' => $GLOBALS['efb_uploads']['basedir'], 'baseurl' => $GLOBALS['efb_uploads']['baseurl'], 'error' => false); } }
if (!function_exists('wp_normalize_path')) { function wp_normalize_path($p) { return preg_replace('#/+#', '/', str_replace('\\', '/', (string) $p)); } }
if (!function_exists('trailingslashit')) { function trailingslashit($s) { return rtrim((string) $s, '/\\') . '/'; } }
if (!function_exists('wp_basename')) { function wp_basename($p) { return basename(str_replace('\\', '/', (string) $p)); } }
if (!function_exists('wp_delete_file')) { function wp_delete_file($f) { @unlink($f); } }
if (!function_exists('delete_transient')) { function delete_transient($k) { unset($GLOBALS['efb_transients'][$k]); return true; } }
if (!function_exists('get_transient')) { function get_transient($k) { return isset($GLOBALS['efb_transients'][$k]) ? $GLOBALS['efb_transients'][$k] : false; } }
if (!function_exists('set_transient')) { function set_transient($k, $v, $t = 0) { $GLOBALS['efb_transients'][$k] = $v; return true; } }
if (!function_exists('delete_option')) { function delete_option($k) { unset($GLOBALS['opts'][$k]); return true; } }
if (!function_exists('wp_date')) { function wp_date($f, $t = null) { return date($f, $t === null ? time() : $t); } }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled($h) { return isset($GLOBALS['efb_cron'][$h]) ? $GLOBALS['efb_cron'][$h] : false; } }
if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event($t, $h) { $GLOBALS['efb_cron'][$h] = $t; return true; } }
if (!function_exists('absint')) { function absint($v) { return abs((int) $v); } }
