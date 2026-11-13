<?php
/**
 * F11-A: the DB 1.6 migration (Install::upgrade_f11_schema()) on the SQLite stand-in.
 *
 *  - EMSFB_DB_VERSION is 1.6 or later; the step sits before the emsfb_stts_ early return.
 *  - A 1.5 site gets emsfb_vrfy_rl_ and emsfb_draft_ (from the real DDL); a second run of
 *    upgrade_schema() applies nothing (idempotent).
 *  - emsfb_msg_ gets KEY form_id only when no index starts with form_id (F13's form_date does).
 *  - A counter table made earlier without KEY scope (F02's original DDL) gets it.
 *  - Install::install() creates both tables on a fresh site.
 *
 * Run: php tests/test-schema-upgrade-f11.php
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
t('EMSFB_DB_VERSION is 1.6 or later', (bool) preg_match('/define\("EMSFB_DB_VERSION",\s*(1\.([6-9]|\d\d)|[2-9]\.\d+)\)/', $main), true);
define('EMSFB_DB_VERSION', 1.6);
$GLOBALS['opts'] = array();
if (!function_exists('get_role')) { function get_role($r) { return null; } }
require_once __DIR__ . '/../includes/class-Emsfb-rate-limit.php';
require_once __DIR__ . '/../includes/class-Emsfb-drafts.php';
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-store.php';
require_once __DIR__ . '/../includes/class-Emsfb-install.php';

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-install.php');
$up = substr($src, strpos($src, 'static function upgrade_schema'));
t('F11 step sits before the early return of upgrade_schema()', strpos($up, 'self::upgrade_f11_schema()') < strpos($up, 'return $applied;'), true);
t('install() creates the F11 tables too', strpos($src, 'self::f11_ddl( $wpdb->prefix, $charset_collate )') !== false, true);

/* A 1.5 site: entries already have F13's form_date, no F11 table. */
$db = $GLOBALS['wpdb'] = new EfbF11SchemaDb();
$db->pdo->exec('CREATE INDEX wp_emsfb_msg__form_date ON wp_emsfb_msg_ (form_id, date)');
$first = \Emsfb\Install::upgrade_f11_schema();
t('first run creates both tables', $first, array('emsfb_vrfy_rl_: CREATE TABLE', 'emsfb_draft_: CREATE TABLE'));
t('emsfb_vrfy_rl_ exists', $db->get_var("SHOW TABLES LIKE 'wp_emsfb_vrfy_rl_'"), 'wp_emsfb_vrfy_rl_');
t('emsfb_draft_ exists with its columns', count($db->pdo->query("PRAGMA table_info('wp_emsfb_draft_')")->fetchAll()), 16);
t('form_date already serves form_id: no extra key', count(array_filter($db->alters, function ($q) { return strpos($q, 'emsfb_msg_') !== false; })), 0);
$db->alters = array();
$second = \Emsfb\Install::upgrade_f11_schema();
t('second run applies nothing', array($second, $db->alters), array(array(), array()));
$all = \Emsfb\Install::upgrade_schema();
t('whole upgrade_schema() a second time: no F11 step', count(array_filter($all, function ($a) { return strpos($a, 'emsfb_vrfy_rl_') === 0 || strpos($a, 'emsfb_draft_') === 0; })), 0);

/* A site whose entries have no index on form_id at all, and an F02-era counter table without KEY scope. */
$db2 = $GLOBALS['wpdb'] = new EfbF11SchemaDb();
$db2->pdo->exec('CREATE TABLE wp_emsfb_vrfy_rl_ (bucket TEXT, scope TEXT, hits INTEGER, window_end TEXT)');
$db2->pdo->exec('CREATE UNIQUE INDEX wp_emsfb_vrfy_rl__pk ON wp_emsfb_vrfy_rl_ (bucket)');
$r2 = \Emsfb\Install::upgrade_f11_schema();
t('no form_id index: KEY form_id added', in_array('emsfb_msg_: ADD KEY form_id', $r2, true), true);
t('counter table without KEY scope: added', in_array('emsfb_vrfy_rl_: ADD KEY scope', $r2, true), true);
t('the existing counter table is not recreated', in_array('emsfb_vrfy_rl_: CREATE TABLE', $r2, true), false);
$db2->alters = array();
t('then idempotent', array(\Emsfb\Install::upgrade_f11_schema(), $db2->alters), array(array(), array()));

/* Fresh install DDL text: F02's columns plus KEY scope; the draft table as in the spec. */
$rl = \Emsfb\Rate_Limit::ddl('wp_', '');
t('counter DDL: bucket char(64) primary key, hits, window_end, KEY window_end, KEY scope', (bool) (preg_match('/`bucket` char\(64\)/', $rl) && preg_match('/PRIMARY KEY  \(bucket\)/', $rl) && preg_match('/KEY window_end \(window_end\)/', $rl) && preg_match('/KEY scope \(scope\)/', $rl)), true);
$dd = \Emsfb\Drafts::ddl('wp_', '');
t('draft DDL: token, owner, listing and expires keys', (bool) (preg_match('/KEY token \(token_hash\)/', $dd) && preg_match('/KEY owner \(fid,kind,owner_hash\)/', $dd) && preg_match('/KEY listing \(fid,kind,status,updated_at\)/', $dd) && preg_match('/KEY expires \(expires_at\)/', $dd)), true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
