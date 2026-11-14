<?php
/**
 * F05-D: the DB 1.9 migration (Install::upgrade_f05_schema()) on the SQLite stand-in.
 *
 *  - EMSFB_DB_VERSION is 1.9 or later; the F05 step sits after F02's and before the emsfb_stts_ early return;
 *    install() creates the table on a fresh site;
 *  - a 1.8 site gets emsfb_stock_ from the real DDL (PRIMARY KEY form_id, field_id, option_id), and the counts of
 *    older booking forms (registered_count) are copied in once (an option remembers it); a second run applies
 *    nothing; the PRIMARY KEY refuses a second row of the same choice (INSERT IGNORE answers 0).
 *
 * Run: php tests/test-stock-schema.php
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
function current_time($t, $gmt = 0) { return gmdate('Y-m-d H:i:s'); }
if (!function_exists('get_role')) { function get_role($r) { return null; } }
function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', (string) $s); }
function emsfb_json_decode_efb($s, $a = true) { return json_decode($s, $a); }
class EfbStockSchemaDb extends EfbF11TestDb {
	public $alters = array();
	public function get_results($q, $output = OBJECT) {
		if (preg_match('/^\s*SHOW INDEX FROM/i', $q)) return array();
		return parent::get_results($q, $output);
	}
	public function query($q) {
		if (preg_match('/^\s*ALTER TABLE/i', $q)) { $this->alters[] = $q; return 1; }
		return parent::query($q);
	}
	public function get_charset_collate() { return ''; }
}
$main = file_get_contents(__DIR__ . '/../emsfb.php');
t('EMSFB_DB_VERSION is 1.9 or later', (bool) preg_match('/define\("EMSFB_DB_VERSION",\s*(1\.(9|\d\d)|[2-9]\.\d+)\)/', $main), true);
define('EMSFB_DB_VERSION', 1.9);
$GLOBALS['opts'] = array();
foreach (array('class-Emsfb-rate-limit.php', 'class-Emsfb-drafts.php', 'payments/class-Emsfb-payment-store.php', 'class-Emsfb-masks.php', 'class-Emsfb-field-rules.php', 'class-Emsfb-unique.php', 'class-Emsfb-choices.php', 'class-Emsfb-stock.php', 'class-Emsfb-install.php') as $f) require_once __DIR__ . '/../includes/' . $f;
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-install.php');
$up = substr($src, strpos($src, 'static function upgrade_schema'));
t('F05 step after F02 and before the early return of upgrade_schema()', strpos($up, 'self::upgrade_f02_schema()') < strpos($up, 'self::upgrade_f05_schema()') && strpos($up, 'self::upgrade_f05_schema()') < strpos($up, 'return $applied;'), true);
t('install() creates the F05 table too', strpos($src, 'self::f05_ddl( $wpdb->prefix, $charset_collate )') !== false, true);
t('the DDL: PRIMARY KEY (form_id, field_id, option_id), used, updated', (bool) preg_match('/PRIMARY KEY\s+\(form_id,field_id,option_id\)/', \Emsfb\Stock::ddl('wp_', '')) && strpos(\Emsfb\Stock::ddl('wp_', ''), '`used` int(11) NOT NULL DEFAULT 0') !== false, true);

$db = $GLOBALS['wpdb'] = new EfbStockSchemaDb();
$db->pdo->exec('CREATE TABLE IF NOT EXISTS wp_emsfb_form (form_id INTEGER PRIMARY KEY, form_structer TEXT)');
$booking = array(array('type' => 'form', 'booking' => '1'), array('id_' => 'slot', 'type' => 'radio'), array('id_' => 'm', 'type' => 'option', 'parent' => 'slot', 'value' => 'Morning', 'mlen' => '4', 'registered_count' => '3'), array('id_' => 'n', 'type' => 'option', 'parent' => 'slot', 'value' => 'Noon', 'mlen' => '9', 'registered_count' => '0'));
 $db->pdo->exec("INSERT INTO wp_emsfb_form (form_id, form_structer) VALUES (5, " . $db->pdo->quote(str_replace('"', '\\"', json_encode($booking))) . ")");
$plain = array(array('type' => 'form'), array('id_' => 'r', 'type' => 'radio'), array('id_' => 'x', 'type' => 'option', 'parent' => 'r', 'value' => 'X', 'registered_count' => '7'));
 $db->pdo->exec("INSERT INTO wp_emsfb_form (form_id, form_structer) VALUES (6, " . $db->pdo->quote(str_replace('"', '\\"', json_encode($plain))) . ")");
$first = \Emsfb\Install::upgrade_f05_schema();
t('a 1.8 site: emsfb_stock_ is created and the booking counts copied (one row: Morning 3; Noon 0 and a non-booking form skipped)', $first, array('emsfb_stock_: CREATE TABLE', 'emsfb_stock_: booking counts (1)'));
t('the copied count', (int) $db->get_var("SELECT used FROM wp_emsfb_stock_ WHERE form_id = 5 AND option_id = 'm'"), 3);
t('no ALTER on any table', $db->alters, array());
t('second run applies nothing', \Emsfb\Install::upgrade_f05_schema(), array());
$ins = "INSERT IGNORE INTO wp_emsfb_stock_ (form_id, field_id, option_id, used, updated) VALUES (5, 'slot', 'm', 0, '2026-01-01 00:00:00')";
t('the PRIMARY KEY refuses a second row of the same choice (INSERT IGNORE = 0)', $db->query(preg_replace('/^INSERT IGNORE/', 'INSERT OR IGNORE', $ins)), 0);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
