<?php
/**
 * SPLIT-G: after Easy Form Builder Data Tools' "Remove" (or a manual DROP) while core stays active, core puts its tables
 * back the WordPress way once (Install::install(), then the upgrade steps), with the database errors of the check
 * suppressed, instead of "table doesn't exist" on every request; the check costs one SHOW TABLES per table at most every
 * 12 hours (5 minutes while something was missing). The method is lifted from includes/class-Emsfb.php.
 *
 * Run: php tests/test-split-schema-quiet.php
 */
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n"; $failed++;
}
define('MINUTE_IN_SECONDS', 60); define('HOUR_IN_SECONDS', 3600);
$GLOBALS['tr'] = array(); $GLOBALS['opt'] = array('Emsfb_db_version' => '1.11');
function get_transient($k) { return isset($GLOBALS['tr'][$k]) ? $GLOBALS['tr'][$k][0] : false; }
function set_transient($k, $v, $ttl = 0) { $GLOBALS['tr'][$k] = array($v, $ttl); return true; }
function delete_transient($k) { unset($GLOBALS['tr'][$k]); return true; }
function delete_option($k) { unset($GLOBALS['opt'][$k]); return true; }
class Split_Fake_Wpdb {
	public $prefix = 'wp_'; public $tables = array(); public $shows = 0; public $suppressed = array();
	public function suppress_errors($on = true) { $was = end($this->suppressed) ?: false; $this->suppressed[] = $on; return $was; }
	public function prepare($q, $a) { return str_replace('%s', $a, $q); }
	public function get_var($q) { $this->shows++; $name = substr($q, strlen('SHOW TABLES LIKE ')); return in_array($name, $this->tables, true) ? $name : null; }
}
$GLOBALS['wpdb'] = new Split_Fake_Wpdb();
$all = array('wp_emsfb_form', 'wp_emsfb_msg_', 'wp_emsfb_rsp_', 'wp_emsfb_stts_', 'wp_emsfb_setting');
eval('namespace Emsfb; class Install { public static $runs = 0; public static function install() { self::$runs++; $GLOBALS["wpdb"]->tables = ' . var_export($all, true) . '; } }');
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb.php');
preg_match('/    public function ensure_tables_efb\(\): void \{.*?\n    \}\n/s', $src, $m);
eval('class SplitSchemaFixture { ' . $m[0] . ' }');
$fx = new SplitSchemaFixture();

$GLOBALS['wpdb']->tables = $all;
$fx->ensure_tables_efb();
t('tables there: nothing installed, the check remembered for 12 hours', array(\Emsfb\Install::$runs, $GLOBALS['tr']['emsfb_tables_ok'][1], $GLOBALS['opt']['Emsfb_db_version']), array(0, 12 * 3600, '1.11'));
$n = $GLOBALS['wpdb']->shows;
$fx->ensure_tables_efb(); $fx->ensure_tables_efb();
t('…the next requests ask the database nothing', $GLOBALS['wpdb']->shows, $n);

// Data Tools' "Remove": every table dropped, core still active.
$GLOBALS['wpdb']->tables = array(); $GLOBALS['tr'] = array();
$fx->ensure_tables_efb();
t('tables removed: Install::install() once, the version forgotten so the upgrade steps run again', array(\Emsfb\Install::$runs, isset($GLOBALS['opt']['Emsfb_db_version']), count($GLOBALS['wpdb']->tables)), array(1, false, 5));
t('…the check ran with database errors suppressed, and looks again in 5 minutes', array($GLOBALS['wpdb']->suppressed[count($GLOBALS['wpdb']->suppressed) - 2], $GLOBALS['tr']['emsfb_tables_ok'][1]), array(true, 5 * 60));
$fx->ensure_tables_efb();
t('…not installed again meanwhile (no loop)', \Emsfb\Install::$runs, 1);
// Another request already rebuilding: this one waits.
$GLOBALS['wpdb']->tables = array(); $GLOBALS['tr'] = array('emsfb_schema_lock' => array(time(), 60));
$fx->ensure_tables_efb();
t('a request that finds the schema lock leaves the work to the one holding it', \Emsfb\Install::$runs, 1);
t('maybe_upgrade_schema_efb() runs the check first', strpos($src, "public function maybe_upgrade_schema_efb(): void {\n        // SPLIT-G") !== false && strpos($src, '$this->ensure_tables_efb();') !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
