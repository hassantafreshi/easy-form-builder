<?php
/**
 * F15-C: the DB 1.11 migration (Install::upgrade_f15_schema(): the form versions table emsfb_form_rev_) on the SQLite
 * stand-in.
 *
 *  - EMSFB_DB_VERSION is the string '1.11' (compared with version_compare: newer than '1.10' and '1.9');
 *  - the F15 step runs after F14's and before the emsfb_stts_ early return of upgrade_schema(); install() creates the
 *    table on a fresh site;
 *  - a 1.10 site WITHOUT emsfb_stts_ (the early return) still gets emsfb_form_rev_ from the whole upgrade_schema(),
 *    with the columns of the DDL; a second run applies nothing (idempotent).
 *
 * Run: php tests/test-upgrade-schema-revisions.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/f11-test-db.php';
function dbDelta($sql) { $GLOBALS['wpdb']->exec_mysql_ddl($sql); $GLOBALS['dbdelta'][] = $sql; return array(); }
if (!function_exists('get_role')) { function get_role($r) { return null; } }
class EfbRevSchemaDb extends EfbF11TestDb {
	public $alters = array();
	public function exec_mysql_ddl($sql) {
		$sql = preg_replace('/rev_id bigint\(20\) unsigned NOT NULL AUTO_INCREMENT/', 'rev_id INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
		$sql = preg_replace('/,\s*PRIMARY KEY\s+\(rev_id\)/', '', $sql);
		return parent::exec_mysql_ddl($sql);
	}
	public function get_results($q, $output = OBJECT) {
		if (preg_match('/^\s*SHOW (INDEX|COLUMNS) FROM/i', $q)) return array();
		return parent::get_results($q, $output);
	}
	public function query($q) {
		if (preg_match('/^\s*ALTER TABLE/i', $q)) { $this->alters[] = $q; return 1; }
		return parent::query($q);
	}
	public function get_charset_collate() { return ''; }
}
$main = file_get_contents(__DIR__ . '/../emsfb.php');
t('EMSFB_DB_VERSION is the string \'1.11\'', (bool) preg_match('/define\("EMSFB_DB_VERSION",\s*\'1\.11\'\)/', $main), true);
t('version_compare: 1.11 is newer than 1.10 and 1.9', array(version_compare('1.11', '1.10', '>'), version_compare('1.11', '1.9', '>')), array(true, true));
define('EMSFB_DB_VERSION', '1.11');
$GLOBALS['opts'] = array();
function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
foreach (array('class-Emsfb-revisions.php', 'class-Emsfb-install.php') as $f) require_once __DIR__ . '/../includes/' . $f;
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-install.php');
$up = substr($src, strpos($src, 'static function upgrade_schema'));
t('F15 step after F14 and before the early return of upgrade_schema()', strpos($up, 'self::upgrade_f14_schema()') < strpos($up, 'self::upgrade_f15_schema()') && strpos($up, 'self::upgrade_f15_schema()') < strpos($up, 'return $applied;'), true);
t('install() creates the F15 table too', strpos($src, 'self::f15_ddl( $wpdb->prefix, $charset_collate )') !== false, true);

$db = $GLOBALS['wpdb'] = new EfbRevSchemaDb();
$db->pdo->exec('DROP TABLE IF EXISTS wp_emsfb_stts_');
t('the site has no emsfb_stts_ (upgrade_schema() returns early there)', $db->get_var("SHOW TABLES LIKE 'wp_emsfb_stts_'"), null);
$first = \Emsfb\Install::upgrade_schema();
t('a 1.10 site without emsfb_stts_: the whole upgrade creates emsfb_form_rev_', in_array('emsfb_form_rev_: CREATE TABLE', $first, true), true);
$cols = array_column($db->pdo->query("PRAGMA table_info('wp_emsfb_form_rev_')")->fetchAll(PDO::FETCH_ASSOC), 'name');
t('the table has the columns of the DDL', $cols, array('rev_id', 'form_id', 'form_name', 'form_type', 'form_structer', 'struct_hash', 'field_count', 'step_count', 'user_id', 'source', 'note', 'created'));
$second = \Emsfb\Install::upgrade_schema();
t('second run: no F15 step (idempotent)', in_array('emsfb_form_rev_: CREATE TABLE', $second, true), false);
t('upgrade_f15_schema() alone a second time applies nothing', \Emsfb\Install::upgrade_f15_schema(), array());
t('no ALTER on any table for F15', count(array_filter($db->alters, function ($q) { return strpos($q, 'form_rev') !== false; })), 0);
\Emsfb\Revisions::reset_ready();
t('Revisions::ready() after the upgrade', \Emsfb\Revisions::ready(), true);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
