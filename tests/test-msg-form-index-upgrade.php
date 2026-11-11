<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Install::upgrade_schema() adds emsfb_msg_.form_date (form_id,date) and
 * emsfb_rsp_.msg_id (DB version 1.4), idempotently and before the
 * emsfb_stts_ early return (IMPLEMENTATION-PLAYBOOK §7).
 *
 * Loads the real Install class against an in-memory $wpdb that understands
 * SHOW TABLES / SHOW COLUMNS / SHOW INDEX / ALTER TABLE ... ADD KEY.
 *
 * Run: php tests/test-msg-form-index-upgrade.php
 */

$pass = 0;
$fail = 0;

function test($label, $actual, $expected) {
    global $pass, $fail;
    if ($actual === $expected) {
        $pass++;
        echo "[PASS] $label\n";
    } else {
        $fail++;
        echo "[FAIL] $label\n";
        echo "  Expected: " . var_export($expected, true) . "\n";
        echo "  Actual:   " . var_export($actual, true) . "\n";
    }
}

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$emsfb = file_get_contents(__DIR__ . '/../emsfb.php');
preg_match('/define\("EMSFB_DB_VERSION",\s*([0-9.]+)\)/', $emsfb, $m);
define('EMSFB_DB_VERSION', (float) $m[1]);

$GLOBALS['efb_options'] = array();
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['efb_options']) ? $GLOBALS['efb_options'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['efb_options'][$k] = $v; return true; }
function add_option($k, $v = '', $dep = '', $a = null) {
    if (array_key_exists($k, $GLOBALS['efb_options'])) return false;
    $GLOBALS['efb_options'][$k] = $v;
    return true;
}
function delete_option($k) { unset($GLOBALS['efb_options'][$k]); return true; }

class Fake_WPDB {
    public $prefix = 'wp_';
    /** table => [index names] ; columns only for emsfb_form.status */
    public $tables = array();
    public $alters = array();

    public function prepare($sql, ...$args) {
        $i = 0;
        return preg_replace_callback('/%[ds]/', function ($m) use (&$i, $args) { return "'" . $args[$i++] . "'"; }, $sql);
    }
    public function get_var($sql) {
        if (preg_match("/SHOW TABLES LIKE '(\\w+)'/", $sql, $m)) return isset($this->tables[$m[1]]) ? $m[1] : null;
        throw new Exception("unexpected get_var $sql");
    }
    public function get_results($sql, $out = null) {
        if (preg_match('/SHOW COLUMNS FROM `(\w+)`/', $sql)) return array((object) array('Field' => 'status'));
        if (preg_match('/SHOW INDEX FROM `(\w+)`/', $sql, $m)) {
            $rows = array();
            foreach ($this->tables[$m[1]] as $k) $rows[] = array('Key_name' => $k);
            return $rows;
        }
        throw new Exception("unexpected get_results $sql");
    }
    public function query($sql) {
        if (preg_match('/ALTER TABLE `(\w+)` ADD KEY `?(\w+)`?/', $sql, $m)) {
            $this->alters[] = $sql;
            $this->tables[$m[1]][] = $m[2];
            return 1;
        }
        throw new Exception("unexpected query $sql");
    }
}

require_once __DIR__ . '/../includes/class-Emsfb-install.php';
use Emsfb\Install;

function fresh_db($with_stts = true) {
    $db = new Fake_WPDB();
    $db->tables = array(
        'wp_emsfb_form' => array('PRIMARY'),
        'wp_emsfb_msg_' => array('PRIMARY'),
        'wp_emsfb_rsp_' => array('PRIMARY'),
    );
    if ($with_stts) $db->tables['wp_emsfb_stts_'] = array('PRIMARY', 'sid', 'lookup', 'date');
    $GLOBALS['wpdb'] = $db;
    $GLOBALS['efb_options'] = array('Emsfb_db_version' => '1.3');
    return $db;
}

echo "--- version ---\n";
test('EMSFB_DB_VERSION moved past 1.3, so existing sites run the step', version_compare((string) EMSFB_DB_VERSION, '1.3', '>'), true);

echo "\n--- first run on an existing 1.3 site ---\n";
$db = fresh_db();
$applied = Install::upgrade_schema();
test('emsfb_msg_ gets form_date (form_id,date)', in_array('form_date', $db->tables['wp_emsfb_msg_'], true), true);
test('emsfb_rsp_ gets msg_id', in_array('msg_id', $db->tables['wp_emsfb_rsp_'], true), true);
test('the ALTER for msg is exactly the F13 §5.4 key', in_array('ALTER TABLE `wp_emsfb_msg_` ADD KEY `form_date` (`form_id`,`date`)', $db->alters, true), true);
test('applied list reports both', $applied, array('emsfb_msg_: ADD KEY `form_date` (`form_id`,`date`)', 'emsfb_rsp_: ADD KEY `msg_id` (`msg_id`)'));
test('version recorded', (string) $GLOBALS['efb_options']['Emsfb_db_version'], (string) EMSFB_DB_VERSION);
test('lock released', array_key_exists('emsfb_schema_lock', $GLOBALS['efb_options']), false);

echo "\n--- repeat runs (other features bump the version again) ---\n";
$before = count($db->alters);
$applied = Install::upgrade_schema();
test('second run applies nothing', array($applied, count($db->alters)), array(array(), $before));
$GLOBALS['efb_options']['Emsfb_db_version'] = '1.3';
Install::upgrade_schema();
test('third run after a version reset still applies nothing', count($db->alters), $before);
test('no duplicate index names', array_count_values($db->tables['wp_emsfb_msg_'])['form_date'], 1);

echo "\n--- emsfb_stts_ missing (early return) ---\n";
$db = fresh_db(false);
Install::upgrade_schema();
test('msg index is still added: the step sits before the early return', in_array('form_date', $db->tables['wp_emsfb_msg_'], true), true);

echo "\n--- tables missing entirely ---\n";
$db = fresh_db();
unset($db->tables['wp_emsfb_msg_'], $db->tables['wp_emsfb_rsp_']);
test('missing tables are skipped without errors', Install::upgrade_schema(), array());

echo "\n--- concurrent request holds the lock ---\n";
$db = fresh_db();
$GLOBALS['efb_options']['emsfb_schema_lock'] = time();
Install::upgrade_schema();
test('no ALTER while another request runs it', $db->alters, array());
test('version is NOT advanced, so the step runs again later', (string) $GLOBALS['efb_options']['Emsfb_db_version'], '1.3');
$GLOBALS['efb_options']['emsfb_schema_lock'] = time() - 120;
Install::upgrade_schema();
test('a stale lock (crashed request) is taken over', in_array('form_date', $db->tables['wp_emsfb_msg_'], true), true);
test('then the version is recorded', (string) $GLOBALS['efb_options']['Emsfb_db_version'], (string) EMSFB_DB_VERSION);

echo "\n--- fresh installs ---\n";
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-install.php');
test('CREATE TABLE emsfb_msg_ declares KEY form_date (form_id,date)', (bool) preg_match('/CREATE TABLE \{\$table_name_msg\}.*?KEY form_date \(form_id,date\)\s*\) \{\$charset_collate\}/s', $src), true);
test('CREATE TABLE emsfb_rsp_ declares KEY msg_id (msg_id)', (bool) preg_match('/CREATE TABLE \{\$table_name_rsp\}.*?KEY msg_id \(msg_id\)\s*\) \{\$charset_collate\}/s', $src), true);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
