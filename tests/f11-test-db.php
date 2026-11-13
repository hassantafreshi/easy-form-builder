<?php
/**
 * Not a test. The F11 tests' database: the PAY00 SQLite $wpdb stand-in (pay-test-db.php, itself
 * built on F13's privacy-test-db.php) plus the F11 tables made from the real DDL
 * (Rate_Limit::ddl(), Drafts::ddl()), and an optional file database so several processes can
 * share it (the limiter's concurrency test). SQLite runs every write under one lock, so a
 * conditional UPDATE is as atomic here as under InnoDB's row lock.
 */
require_once __DIR__ . '/pay-test-db.php';

class EfbF11TestDb extends EfbPayTestDb {
	public function __construct($file = null) {
		if ($file === null) {
			parent::__construct();
		} else {
			$this->pdo = new PDO('sqlite:' . $file);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
			$this->pdo->setAttribute(PDO::ATTR_TIMEOUT, 30);
			$this->pdo->exec('PRAGMA busy_timeout = 30000');
		}
	}

	/** The F11 tables from the plugin's own CREATE TABLE statements. */
	public function create_f11_tables() {
		$this->exec_mysql_ddl(\Emsfb\Rate_Limit::ddl($this->prefix, ''));
		$this->exec_mysql_ddl(\Emsfb\Drafts::ddl($this->prefix, ''));
	}

	public function exec_mysql_ddl($sql) {
		// The F11 DDL quotes column names and adds COLLATE: the PAY00 converter does not expect either.
		$sql = str_replace('`', '', $sql);
		$sql = preg_replace('/ COLLATE \w+/', '', $sql);
		// A PRIMARY KEY on a plain column (bucket) must still refuse a duplicate (INSERT IGNORE).
		$sql = preg_replace('/PRIMARY KEY\s+\((?!id\))(\w+)\)/', 'UNIQUE KEY pk_$1 ($1)', $sql);
		return parent::exec_mysql_ddl($sql);
	}
}
