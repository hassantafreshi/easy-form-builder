<?php
/**
 * UPG-01 hand-off (B10 → B7): a 4.3.1 site's emsfb_msg_ gets everything DB 1.4, 1.10 and 1.12 add with as few table
 * rebuilds as the server allows (Install::upgrade_msg_table(), first in Install::upgrade_schema()).
 *
 * B10's run of 4.3.1 → 4.3.2 on MySQL 8.4 with 1,000,000 entries: five ALTERs on emsfb_msg_, two of them rebuilding
 * the table (12.5 s and 10.5 s), 28.3 s in all, against the 30 s release criterion. Now:
 *  - the eight columns in ONE ALTER with ALGORITHM=INSTANT (no copy), then the six keys in ONE ALTER (in place);
 *  - a server that refuses INSTANT (MySQL 5.7, older MariaDB): the columns and keys in ONE ALTER (one rebuild);
 *  - a server that refuses that too: the per-version steps add each part (the existing fallback);
 *  - the per-version steps that follow find everything and issue nothing; a second run issues nothing;
 *  - the old entries keep their content and read the new columns' defaults.
 * On the SQLite stand-in of the F14 tests (tests/entries-test-db.php), with SHOW COLUMNS FROM (no LIKE) added.
 * The timing on MySQL / MariaDB: tests/upgrade/run-upgrade-431.sh (B7's run: 28.3 s → 5.9 s on MySQL 8.4, 1M entries).
 *
 * Run: php tests/test-schema-msg-one-alter.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/entries-test-db.php';
function dbDelta($sql) { $GLOBALS['wpdb']->exec_mysql_ddl($sql); return array(); }
if (!function_exists('get_role')) { function get_role($r) { return null; } }
function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', (string) $s); }
function emsfb_json_decode_efb($s, $a = true) { return json_decode($s, $a); }
define('EMSFB_DB_VERSION', '1.13');
$GLOBALS['opts'] = array();
foreach (array('class-Emsfb-rate-limit.php', 'class-Emsfb-drafts.php', 'payments/class-Emsfb-payment-store.php', 'class-Emsfb-masks.php', 'class-Emsfb-field-rules.php', 'class-Emsfb-unique.php', 'class-Emsfb-choices.php', 'class-Emsfb-stock.php', 'admin/class-Emsfb-entries.php', 'class-Emsfb-install.php') as $f) require_once __DIR__ . '/../includes/' . $f;

/** The stand-in plus SHOW COLUMNS FROM t (every column), and a server that refuses INSTANT or multi-clause ALTERs. */
class EfbOneAlterDb extends EfbEntriesTestDb {
	public $refuse_instant = false;
	public function get_results($q, $output = OBJECT) {
		if (preg_match('/^\s*SHOW COLUMNS FROM `?(\w+)`?\s*$/i', $q, $m)) {
			$rows = array();
			foreach ($this->pdo->query('PRAGMA table_info(' . $m[1] . ')')->fetchAll(PDO::FETCH_ASSOC) as $c) $rows[] = array('Field' => $c['name'], 'Type' => $c['type']);
			return $output === ARRAY_A ? $rows : array_map(function ($r) { return (object) $r; }, $rows);
		}
		return parent::get_results($q, $output);
	}
	public function query($q) {
		if (preg_match('/^\s*ALTER TABLE/i', $q) && stripos($q, 'ALGORITHM=INSTANT') !== false) {
			if ($this->refuse_instant) { $this->alters[] = $q; return false; }
			$r = parent::query(preg_replace('/,\s*ALGORITHM=INSTANT\s*$/i', '', $q));
			$this->alters[count($this->alters) - 1] = $q; // as sent
			return $r;
		}
		return parent::query($q);
	}
	/** emsfb_msg_ as 4.3.1 (DB 1.3) made it, with three entries. */
	public function msg_431() {
		$this->pdo->exec('DROP TABLE IF EXISTS wp_emsfb_msg_');
		$this->pdo->exec('CREATE TABLE wp_emsfb_msg_ (msg_id INTEGER PRIMARY KEY AUTOINCREMENT, form_id INTEGER NOT NULL, track TEXT NOT NULL, ip TEXT NOT NULL DEFAULT \'\', form_title_x TEXT NOT NULL DEFAULT \'\', content TEXT NOT NULL, date TEXT NOT NULL, read_date TEXT, read_ INTEGER NOT NULL DEFAULT 0, read_by TEXT NOT NULL DEFAULT \'\')');
		$this->pdo->exec("INSERT INTO wp_emsfb_msg_ (form_id, track, content, date, read_) VALUES (3, 'A', '[{\\\"id_\\\":\\\"x\\\",\\\"value\\\":\\\"1\\\"}]', '2026-11-01 10:00:00', 0), (3, 'B', '[]', '2026-11-01 11:00:00', 1), (4, 'C', '[]', '2026-11-02 09:00:00', 4)");
		foreach ($this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'wp_emsfb_msg_'")->fetchAll(PDO::FETCH_COLUMN) as $ix) if (strpos($ix, 'sqlite_') !== 0) $this->pdo->exec("DROP INDEX {$ix}");
		$this->alters = array();
	}
	public function msg_alters() {
		return array_values(array_filter($this->alters, function ($q) { return strpos($q, 'wp_emsfb_msg_`') !== false || preg_match('/ALTER TABLE wp_emsfb_msg_\b/', $q); }));
	}
	public function cols() {
		return array_map(function ($r) { return $r['name']; }, $this->pdo->query('PRAGMA table_info(wp_emsfb_msg_)')->fetchAll(PDO::FETCH_ASSOC));
	}
	public function keys() {
		$k = array();
		foreach ($this->pdo->query('PRAGMA index_list(wp_emsfb_msg_)')->fetchAll(PDO::FETCH_ASSOC) as $ix) $k[] = preg_replace('/^wp_emsfb_msg__/', '', $ix['name']);
		sort($k);
		return $k;
	}
}

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-install.php');
$up = substr($src, strpos($src, 'static function upgrade_schema'));
t('upgrade_schema() runs upgrade_msg_table() before the per-version steps', strpos($up, 'self::upgrade_msg_table()') !== false && strpos($up, 'self::upgrade_msg_table()') < strpos($up, 'self::upgrade_privacy_schema()'), true);

$want_cols = array('uid', 'prv_state', 'entry_status', 'starred', 'trashed_at', 'edited_at', 'owner_state', 'owner_state_at');
$want_keys = array('date', 'form_date', 'form_status_date', 'read_', 'track', 'uid');
t('Install::MSG_COLUMNS covers 1.4, 1.10 (Entries::MSG_COLUMNS) and 1.12', array_keys(\Emsfb\Install::MSG_COLUMNS), $want_cols);
foreach (\Emsfb\Entries::MSG_COLUMNS as $c => $d) t("… the same definition as F14 for {$c}", \Emsfb\Install::MSG_COLUMNS[$c], $d);
foreach (\Emsfb\Entries::MSG_KEYS as $k => $d) t("… the same key as F14 for {$k}", \Emsfb\Install::MSG_KEYS[$k], $d);

/* ---- a server with INSTANT (MySQL 8, MariaDB 10.3+) ---- */
$db = $GLOBALS['wpdb'] = new EfbOneAlterDb();
$db->create_f14_tables(false);
$db->msg_431();
$applied = \Emsfb\Install::upgrade_schema();
$ma = $db->msg_alters();
t('INSTANT server: two ALTERs on emsfb_msg_ (was five)', count($ma), 2);
t('… the first adds the eight columns with ALGORITHM=INSTANT', isset($ma[0]) && substr_count($ma[0], 'ADD COLUMN') === 8 && stripos($ma[0], 'ALGORITHM=INSTANT') !== false && strpos($ma[0], 'ADD KEY') === false, true);
t('… the second adds the six keys, no column', isset($ma[1]) && substr_count($ma[1], 'ADD KEY') === 6 && strpos($ma[1], 'ADD COLUMN') === false, true);
$c = $db->cols();
t('… every new column is there', array_values(array_intersect($want_cols, $c)), $want_cols);
t('… every new key is there', array_values(array_intersect($want_keys, $db->keys())), $want_keys);
t('… reported once each', count(array_filter($applied, function ($a) { return strpos($a, 'emsfb_msg_:') === 0; })), 14);
t('… the entries keep their content and read the defaults', $db->pdo->query("SELECT track || '|' || content || '|' || entry_status || starred || prv_state || owner_state || uid FROM wp_emsfb_msg_ ORDER BY msg_id")->fetchAll(PDO::FETCH_COLUMN), array('A|[{\\"id_\\":\\"x\\",\\"value\\":\\"1\\"}]|00000', 'B|[]|00000', 'C|[]|00000'));
$db->alters = array();
\Emsfb\Install::upgrade_schema();
t('… a second run issues no ALTER on emsfb_msg_', $db->msg_alters(), array());

/* ---- a server without INSTANT (MySQL 5.7) ---- */
$db = $GLOBALS['wpdb'] = new EfbOneAlterDb();
$db->refuse_instant = true;
$db->create_f14_tables(false);
$db->msg_431();
\Emsfb\Install::upgrade_schema();
$ma = $db->msg_alters();
t('no INSTANT: the refused INSTANT try, then ONE ALTER with the eight columns and the six keys (one rebuild)', count($ma) === 2 && stripos($ma[0], 'INSTANT') !== false && substr_count($ma[1], 'ADD COLUMN') === 8 && substr_count($ma[1], 'ADD KEY') === 6, true);
t('… everything there', array(array_values(array_intersect($want_cols, $db->cols())), array_values(array_intersect($want_keys, $db->keys()))), array($want_cols, $want_keys));

/* ---- a server that refuses multi-clause ALTERs too ---- */
$db = $GLOBALS['wpdb'] = new EfbOneAlterDb();
$db->refuse_instant = true;
$db->refuse_multi = true;
$db->create_f14_tables(false);
$db->msg_431();
\Emsfb\Install::upgrade_schema();
t('neither: the per-version steps still add every column and key, one clause at a time', array(array_values(array_intersect($want_cols, $db->cols())), array_values(array_intersect($want_keys, $db->keys()))), array($want_cols, $want_keys));

/* ---- a site that already has everything ---- */
$db->alters = array();
t('a site on 1.13: upgrade_msg_table() applies nothing', \Emsfb\Install::upgrade_msg_table(), array());
t('… and issues no ALTER', $db->msg_alters(), array());

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
