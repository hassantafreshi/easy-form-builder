<?php
/**
 * F03-D: the DB 1.7 migration (Install::upgrade_f03_schema()) on the SQLite stand-in.
 *
 *  - EMSFB_DB_VERSION is 1.7 or later; the step sits before the emsfb_stts_ early return and
 *    after F11's; install() creates the table on a fresh site.
 *  - A 1.6 site gets emsfb_uniq_ from the real DDL (UNIQUE KEY fv (form_id, field_id, vhash), KEY msg),
 *    a second run applies nothing; no index is added to emsfb_msg_ (form_date already starts with
 *    form_id: open question §19.2 resolved).
 *  - The UNIQUE KEY refuses a second claim of the same value (INSERT IGNORE answers 0 rows).
 *
 * Run: php tests/test-unique-schema.php
 */
define('ABSPATH', __DIR__ . '/');

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}

require __DIR__ . '/f11-test-db.php';
function dbDelta($sql) { $GLOBALS['wpdb']->exec_mysql_ddl($sql); $GLOBALS['dbdelta'][] = $sql; return array(); }

/** SHOW INDEX for SQLite, in MySQL's shape (Key_name, Seq_in_index, Column_name). */
class EfbF11SchemaDb extends EfbF11TestDb {
	public function exec_mysql_ddl($sql) { return parent::exec_mysql_ddl(preg_replace('/ CHARACTER SET \\w+/', '', $sql)); }
	public $alters = array();
	public function get_results($q, $output = OBJECT) {
		if (preg_match('/^\s*SHOW INDEX FROM `?(\w+)`?/i', $q, $m)) {
			$rows = array(array('Key_name' => 'PRIMARY', 'Seq_in_index' => 1, 'Column_name' => 'id'));
			foreach ($this->pdo->query("PRAGMA index_list('{$m[1]}')")->fetchAll(PDO::FETCH_ASSOC) as $ix) {
				$name = preg_replace('/^' . preg_quote($m[1], '/') . '_/', '', $ix['name']);
				foreach ($this->pdo->query("PRAGMA index_info('{$ix['name']}')")->fetchAll(PDO::FETCH_ASSOC) as $col) {
					$rows[] = array('Key_name' => $name, 'Seq_in_index' => (int) $col['seqno'] + 1, 'Column_name' => $col['name']);
				}
			}
			return $output === ARRAY_A ? $rows : array_map(function ($r) { return (object) $r; }, $rows);
		}
		return parent::get_results($q, $output);
	}
	public function query($q) {
		if (preg_match('/^\s*ALTER TABLE `?(\w+)`? ADD KEY `?(\w+)`? \((.*)\)/i', $q, $m)) {
			$this->alters[] = $q;
			return $this->pdo->exec("CREATE INDEX {$m[1]}_{$m[2]} ON {$m[1]} (" . str_replace('`', '', $m[3]) . ')') === false ? false : 1;
		}
		if (preg_match('/^\s*ALTER TABLE/i', $q)) { $this->alters[] = $q; return 1; }
		return parent::query($q);
	}
	public function get_charset_collate() { return ''; }
}

$main = file_get_contents(__DIR__ . '/../emsfb.php');

$main = file_get_contents(__DIR__ . '/../emsfb.php');
t('EMSFB_DB_VERSION is 1.7 or later', (bool) preg_match('/define\("EMSFB_DB_VERSION",\s*(1\.([7-9]|\d\d)|[2-9]\.\d+)\)/', $main), true);
define('EMSFB_DB_VERSION', 1.7);
$GLOBALS['opts'] = array();
if (!function_exists('get_role')) { function get_role($r) { return null; } }
foreach (array('class-Emsfb-rate-limit.php', 'class-Emsfb-drafts.php', 'payments/class-Emsfb-payment-store.php', 'class-Emsfb-masks.php', 'class-Emsfb-field-rules.php', 'class-Emsfb-unique.php', 'class-Emsfb-install.php') as $f) require_once __DIR__ . '/../includes/' . $f;

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-install.php');
$up = substr($src, strpos($src, 'static function upgrade_schema'));
t('F03 step after F11 and before the early return of upgrade_schema()', strpos($up, 'self::upgrade_f11_schema()') < strpos($up, 'self::upgrade_f03_schema()') && strpos($up, 'self::upgrade_f03_schema()') < strpos($up, 'return $applied;'), true);
t('install() creates the F03 table too', strpos($src, 'self::f03_ddl( $wpdb->prefix, $charset_collate )') !== false, true);

$db = $GLOBALS['wpdb'] = new EfbF11SchemaDb();
$db->pdo->exec('CREATE INDEX wp_emsfb_msg__form_date ON wp_emsfb_msg_ (form_id, date)');
\Emsfb\Install::upgrade_f11_schema();
$db->alters = array();
$first = \Emsfb\Install::upgrade_f03_schema();
t('a 1.6 site: emsfb_uniq_ is created', $first, array('emsfb_uniq_: CREATE TABLE'));
t('emsfb_uniq_ exists with 6 columns', count($db->pdo->query("PRAGMA table_info('wp_emsfb_uniq_')")->fetchAll()), 6);
t('no ALTER on the entries (form_date serves form_id)', $db->alters, array());
t('second run applies nothing', \Emsfb\Install::upgrade_f03_schema(), array());
$all = \Emsfb\Install::upgrade_schema();
t('whole upgrade_schema() again: no F03 step', count(array_filter($all, function ($a) { return strpos($a, 'emsfb_uniq_') === 0; })), 0);
$q = "INSERT IGNORE INTO wp_emsfb_uniq_ (form_id, field_id, vhash, date) VALUES (5, 'em', '" . str_repeat('a', 64) . "', '2026-11-13')";
t('UNIQUE KEY fv: the first claim takes the row, the same claim again takes none', array($db->query($q), $db->query($q)), array(1, 0));
$d = \Emsfb\Unique_Values::ddl('wp_', '');
t('DDL as the spec: vhash char(64) ascii_bin, msg_id NULL, UNIQUE KEY fv, KEY msg', (bool) (preg_match('/`vhash` char\(64\) CHARACTER SET ascii COLLATE ascii_bin NOT NULL/', $d) && preg_match('/`msg_id` int\(11\) NULL DEFAULT NULL/', $d) && preg_match('/UNIQUE KEY fv \(form_id,field_id,vhash\)/', $d) && preg_match('/KEY msg \(msg_id\)/', $d)), true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
