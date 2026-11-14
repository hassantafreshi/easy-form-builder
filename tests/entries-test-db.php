<?php
/**
 * Not a test. The F14 tests' database: the F11 SQLite $wpdb stand-in (f11-test-db.php, built on PAY00's and
 * F13's) with emsfb_msg_ as DB 1.10 has it and the three F14 tables from the real Entries::ddl(). Also:
 *  - SHOW INDEX FROM (from SQLite's own index list, Key_name / Column_name / Seq_in_index);
 *  - ALTER TABLE with several clauses (ADD COLUMN, ADD KEY) applied one by one, like MySQL does in one go;
 *    $refuse_multi = true answers false to a statement with more than one clause (the fallback path);
 *  - MD5() / DATE() in SQL;
 *  - the WordPress functions the Entries class calls.
 */
require_once __DIR__ . '/f11-test-db.php';

class EfbEntriesTestDb extends EfbF11TestDb {
	public $alters = array();
	public $refuse_multi = false;

	public function __construct($file = null) {
		parent::__construct($file);
		if (method_exists($this->pdo, 'sqliteCreateFunction')) {
			$this->pdo->sqliteCreateFunction('MD5', 'md5', 1);
		}
	}

	/** emsfb_msg_ of DB 1.10 and the F14 tables. */
	public function create_f14_tables($with_columns = true) {
		if ($with_columns) {
			foreach (\Emsfb\Entries::MSG_COLUMNS as $c => $def) $this->pdo->exec("ALTER TABLE wp_emsfb_msg_ ADD COLUMN {$c} " . (stripos($def, 'TINYINT') !== false ? 'INTEGER NOT NULL DEFAULT 0' : 'TEXT NULL DEFAULT NULL'));
			$this->pdo->exec('CREATE INDEX wp_emsfb_msg__form_status_date ON wp_emsfb_msg_ (form_id, entry_status, date)');
		}
		foreach (\Emsfb\Entries::ddl($this->prefix, '') as $sql) $this->exec_mysql_ddl($sql);
	}

	public function exec_mysql_ddl($sql) {
		// emsfb_stats_ has a two-column PRIMARY KEY: keep it as a UNIQUE index so INSERT IGNORE refuses a second row.
		$sql = preg_replace('/PRIMARY KEY\s+\((\w+),(\w+)\)/', 'UNIQUE KEY pk_$1_$2 ($1,$2)', $sql);
		$sql = preg_replace('/\bdate NOT NULL/', 'TEXT NOT NULL', $sql);
		return parent::exec_mysql_ddl($sql);
	}

	public function get_results($q, $output = OBJECT) {
		if (preg_match('/^\s*SHOW INDEX FROM `?(\w+)`?/i', $q, $m)) {
			$rows = array();
			foreach ($this->pdo->query('PRAGMA index_list(' . $m[1] . ')')->fetchAll(PDO::FETCH_ASSOC) as $ix) {
				$name = preg_replace('/^' . preg_quote($m[1], '/') . '_/', '', $ix['name']);
				foreach ($this->pdo->query('PRAGMA index_info(' . $ix['name'] . ')')->fetchAll(PDO::FETCH_ASSOC) as $col) {
					$rows[] = array('Key_name' => $name, 'Column_name' => $col['name'], 'Seq_in_index' => (int) $col['seqno'] + 1);
				}
			}
			return $output === ARRAY_A ? $rows : array_map(function ($r) { return (object) $r; }, $rows);
		}
		return parent::get_results($q, $output);
	}

	public function query($q) {
		if (preg_match('/^\s*ALTER TABLE `?(\w+)`?\s+(.*)$/is', $q, $m)) {
			$this->alters[] = $q;
			$clauses = preg_split('/,\s*(?=ADD )/', trim($m[2]));
			if ($this->refuse_multi && count($clauses) > 1) return false;
			foreach ($clauses as $c) {
				if (preg_match('/^ADD COLUMN `?(\w+)`?\s+(.*)$/i', $c, $cm)) {
					$type = stripos($cm[2], 'TINYINT') !== false || stripos($cm[2], 'INT') !== false ? 'INTEGER NOT NULL DEFAULT 0' : 'TEXT NULL DEFAULT NULL';
					if ($this->pdo->exec("ALTER TABLE {$m[1]} ADD COLUMN {$cm[1]} {$type}") === false) return false;
				} elseif (preg_match('/^ADD KEY `?(\w+)`?\s*\((.*)\)$/i', $c, $km)) {
					if ($this->pdo->exec("CREATE INDEX {$m[1]}_{$km[1]} ON {$m[1]} (" . str_replace('`', '', $km[2]) . ')') === false) return false;
				} else {
					return false;
				}
			}
			return 1;
		}
		return parent::query($q);
	}
}

if (!function_exists('wp_date')) { function wp_date($f, $t = null) { return date($f, $t === null ? time() : $t); } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return isset($GLOBALS['efb_uid']) ? (int) $GLOBALS['efb_uid'] : 1; } }
if (!function_exists('current_user_can')) { function current_user_can($c) { return isset($GLOBALS['efb_caps'][$c]) ? (bool) $GLOBALS['efb_caps'][$c] : false; } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return get_current_user_id() > 0; } }
if (!function_exists('get_user_meta')) { function get_user_meta($u, $k, $s = false) { return isset($GLOBALS['efb_umeta'][$u][$k]) ? $GLOBALS['efb_umeta'][$u][$k] : ''; } }
if (!function_exists('update_user_meta')) { function update_user_meta($u, $k, $v) { $GLOBALS['efb_umeta'][$u][$k] = $v; return true; } }
if (!function_exists('get_userdata')) { function get_userdata($id) { return (object) array('ID' => $id, 'display_name' => 'User ' . $id); } }
if (!function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($s) { return is_scalar($s) ? trim(strip_tags((string) $s)) : ''; } }
if (!function_exists('wp_unslash')) { function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; } }
if (!function_exists('absint')) { function absint($v) { return abs((int) $v); } }
